<?php

namespace App\Services\Import;

use App\Services\Ai\AiDataCheckerParser;
use App\Services\Ai\AiDataCheckerService;
use App\Services\Ai\AiDatasetSummaryBuilder;
use App\Services\Export\CsvExporter;
use App\Services\Export\TemplateLoader;
use App\Services\Export\XlsxExporter;
use App\Models\BrandSkuSetting;
use App\Services\Sku\LiewoodSkuGenerator;
use App\Services\Supplier\SupplierMappingLoader;
use App\Services\Import\ConfirmedRuleApplier;
use App\Services\Import\SupplierTypeClassificationResolver;
use App\Services\Import\NormalizationRulesRepository;
use App\Services\Import\ImportInstructionService;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ConversionPipeline
{
    public function __construct(
        private readonly SupplierMappingLoader $mappingLoader,
        private readonly TemplateLoader $templateLoader,
        private readonly ExcelParser $parser,
        private readonly RowNormalizer $normalizer,
        private readonly RowMapper $mapper,
        private readonly ImportValidator $validator,
        private readonly XlsxExporter $xlsxExporter,
        private readonly CsvExporter $csvExporter,
        private readonly AiDatasetSummaryBuilder $aiSummaryBuilder,
        private readonly AiDataCheckerService $aiChecker,
        private readonly AiDataCheckerParser $aiCheckerParser,
        private readonly NormalizationRulesRepository $normalizationRulesRepo,
        private readonly ConfirmedRuleApplier $ruleApplier,
        private readonly LiewoodSkuGenerator $liewoodSkuGenerator,
        private readonly LiewoodGoogleDriveImageFiller $liewoodGoogleDriveImageFiller,
        private readonly LiewoodRetailTransformer $liewoodRetailTransformer,
        private readonly SupplierTypeClassificationResolver $supplierTypeClassificationResolver,
        private readonly ImportInstructionService $importInstructionService,
    ) {
    }

    /**
     * @return array{job_id:string, preview:array<string,mixed>}
     */
    public function run(
        string $supplierCode,
        string $inputPath,
        string $originalFilename,
        bool $exportXlsx,
        bool $exportCsv,
        ?array $overrideColumnMapping = null,
        ?bool $liewoodGcsUseDownloadProxy = null,
        ?string $liewoodGcsPrefix = null,
    ): array
    {
        $jobId = (string) Str::uuid();
        $jobDir = rtrim((string)config('product_import.jobs_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $jobId;

        if (!is_dir($jobDir)) {
            mkdir($jobDir, 0775, true);
        }

        $templatePath = (string) config('product_import.template_path');
        $templateColumns = $this->templateLoader->loadTemplateColumns($templatePath);

        $liewoodRetail = $supplierCode === 'liewood'
            && (bool) config('liewood_retail.enabled', true);
        if ($liewoodRetail) {
            $retailCols = config('liewood_retail.template_column_keys');
            if (is_array($retailCols) && $retailCols !== []) {
                $templateColumns = array_values($retailCols);
            }
        }

        $supplierCfg = $this->mappingLoader->load($supplierCode);
        $columnMapping = is_array($overrideColumnMapping) ? $overrideColumnMapping : ($supplierCfg['column_mapping'] ?? []);

        $parsed = $this->parser->parse($inputPath, $supplierCfg['sheet'] ?? null);

        $normalizedRows = $this->normalizer->normalizeRows($parsed['rows']);

        $mapped = $this->mapper->map($normalizedRows, $columnMapping, $templateColumns);
        $outputRows = $mapped['output_rows'];
        $outputRows = $this->applyCriticalFallbacks($supplierCode, $normalizedRows, $outputRows);

        if ($liewoodRetail) {
            [$dbFootwear, $dbHat, $dbSocks] = $this->supplierTypeClassificationResolver->extraListsForLiewood($supplierCode);
            $outputRows = $this->liewoodRetailTransformer->transform($outputRows, $normalizedRows, $dbFootwear, $dbHat, $dbSocks);
        }

        // Deterministic SKU generation for suppliers that require it.
        $outputRows = $this->applyDeterministicSkuGeneration($supplierCode, $outputRows, $liewoodRetail);

        // Liewood: optional image URLs by filename (LW##### + Color Code), source via config.
        if ($supplierCode === 'liewood' && $liewoodGcsPrefix !== '__NO_PHOTO__') {
            $photoSlots = $liewoodRetail
                ? (array) config('liewood_retail.photo_slots', [])
                : null;
            $outputRows = $this->liewoodGoogleDriveImageFiller->fillExportRows(
                $outputRows,
                $photoSlots,
                $liewoodGcsUseDownloadProxy,
                $liewoodGcsPrefix,
            );
        }

        $missingSourceColumns = [];
        foreach (($mapped['mapping_status'] ?? []) as $ms) {
            if (($ms['status'] ?? '') === 'missing_source') {
                $missingSourceColumns[] = (string)($ms['source'] ?? '');
            }
        }

        $baseRequired = (array) config('product_import.validation.required_columns', []);
        $mergedRequired = $liewoodRetail
            ? array_merge($baseRequired, (array) config('liewood_retail.validation', []))
            : $baseRequired;
        $requiredCols = [
            'sku' => (string)($mergedRequired['sku'] ?? 'Sku'),
            'name' => (string)($mergedRequired['name'] ?? 'Product name (EN)'),
            'price' => (string)($mergedRequired['price'] ?? 'Wholesale Price EUR'),
        ];

        // Validate required columns exist in template (impossible state otherwise).
        $templateSet = array_fill_keys($templateColumns, true);
        foreach ($requiredCols as $col) {
            if (!isset($templateSet[$col])) {
                throw new InvalidArgumentException("Required template column not found: {$col}");
            }
        }

        // Apply saved normalization rules (if any). Strict: rules only mutate values, never columns.
        $savedNormRules = $this->normalizationRulesRepo->loadForSupplier($supplierCode);
        $savedNormApplied = $savedNormRules !== [];
        if ($savedNormApplied) {
            $applied = $this->ruleApplier->apply($outputRows, $savedNormRules);
            $outputRows = $applied['rows'];
        }

        $validation = $this->validator->validate($outputRows, $requiredCols);

        // Export all non-empty rows: both "valid" and "warning" rows are included.
        // Only completely empty rows (classified as errors) are excluded.
        $rowsForExport = array_merge($validation['valid_rows'], $validation['warning_rows']);

        // AI data checker is disabled; keep structure for UI but without calling external services.
        $ai = [
            'ok' => false,
            'unavailable' => 'AI data checker is disabled.',
            'warnings' => [],
            'raw' => null,
            'payload' => null,
        ];

        $exportPaths = [
            'xlsx' => null,
            'csv' => null,
            'instruction_csv' => null,
            'instruction_passport_json' => null,
        ];

        $csvHeaderRow = null;
        if ($liewoodRetail) {
            $hdr = config('liewood_retail.csv_header_row');
            if (is_array($hdr) && count($hdr) === count($templateColumns)) {
                $csvHeaderRow = array_values($hdr);
            }
        }

        if ($exportXlsx) {
            $xlsxName = (string) config('product_import.export.xlsx_name', 'output.xlsx');
            $xlsxPath = $jobDir . DIRECTORY_SEPARATOR . $xlsxName;
            $this->xlsxExporter->exportToPath($templateColumns, $rowsForExport, $xlsxPath, $csvHeaderRow);
            $exportPaths['xlsx'] = $xlsxName;
        }

        if ($exportCsv) {
            $csvName = (string) config('product_import.export.csv_name', 'output.csv');
            $csvPath = $jobDir . DIRECTORY_SEPARATOR . $csvName;
            $this->csvExporter->exportToPath($templateColumns, $rowsForExport, $csvPath, $csvHeaderRow);
            $exportPaths['csv'] = $csvName;
        }

        $preview = [
            'job_id' => $jobId,
            'input' => [
                'original_filename' => $originalFilename,
                'supplier_code' => $supplierCfg['supplier_code'],
                'supplier_name' => $supplierCfg['supplier_name'],
                'sheet_index' => $parsed['sheet_index'],
            ],
            'counts' => [
                'rows_read' => $parsed['rows_read'],
                'rows_total_output' => $validation['summary']['rows_total'],
                'rows_exported' => count($rowsForExport),
                'warning_count' => count($validation['warnings']),
                'error_count' => count($validation['errors']),
            ],
            'template' => [
                'columns' => $templateColumns,
            ],
            'mapping' => [
                'pairs' => $mapped['mapping_status'],
                'missing_source_columns' => $missingSourceColumns,
            ],
            'preview_rows' => array_slice($rowsForExport, 0, 30),
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'deterministic' => [
                'counts' => $validation['counts'] ?? [],
            ],
            'ai' => [
                'ok' => $ai['ok'],
                'unavailable' => $ai['unavailable'],
                'warnings' => $ai['warnings'],
            ],
            'normalization' => [
                'saved_rules_applied' => $savedNormApplied,
            ],
            'exports' => $exportPaths,
        ];

        $previewJsonName = (string) config('product_import.export.preview_json', 'preview.json');
        file_put_contents($jobDir . DIRECTORY_SEPARATOR . $previewJsonName, json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $passport = $this->importInstructionService->buildPassport(
            (string) ($supplierCfg['supplier_code'] ?? $supplierCode),
            (string) ($supplierCfg['supplier_name'] ?? $supplierCode),
            $templateColumns,
            (array) ($mapped['mapping_status'] ?? []),
            [
                'liewood_retail' => $liewoodRetail,
                'export_xlsx' => $exportXlsx,
                'export_csv' => $exportCsv,
                'example_row' => $rowsForExport[0] ?? [],
            ],
        );
        $instructionRows = $this->importInstructionService->buildInstructionRows($passport);
        $passportJsonName = (string) config('product_import.export.instruction_passport_json', 'instruction_passport.json');
        $instructionCsvName = (string) config('product_import.export.instruction_csv_name', 'instruction.csv');
        $this->importInstructionService->writePassportJson($passport, $jobDir . DIRECTORY_SEPARATOR . $passportJsonName);
        $this->importInstructionService->writeInstructionCsv($instructionRows, $jobDir . DIRECTORY_SEPARATOR . $instructionCsvName);
        $exportPaths['instruction_csv'] = $instructionCsvName;
        $exportPaths['instruction_passport_json'] = $passportJsonName;

        return [
            'job_id' => $jobId,
            'preview' => $preview,
        ];
    }

    /**
     * Apply deterministic non-AI fallbacks for critical fields.
     * Values are copied only from source rows (never invented).
     *
     * @param array<int,array<string,string>> $sourceRows
     * @param array<int,array<string,string>> $outputRows
     * @return array<int,array<string,string>>
     */
    private function applyCriticalFallbacks(string $supplierCode, array $sourceRows, array $outputRows): array
    {
        if ($supplierCode !== 'liewood' && $supplierCode !== 'bobo_choses') {
            return $outputRows;
        }

        foreach ($outputRows as $idx => $row) {
            $src = $sourceRows[$idx] ?? [];

            if ($supplierCode === 'liewood') {
                $brand = trim((string)($src['Brand'] ?? ''));
                foreach (['Brend', 'Brend  '] as $bk) {
                    if (array_key_exists($bk, $row) && trim((string)($row[$bk] ?? '')) === '' && $brand !== '') {
                        $row[$bk] = $brand;
                    }
                }

                $styleNo = trim((string)($src['Style No'] ?? ''));
                // Retail template: "Supplier product ID"; legacy master: "Supplier Product ID"
                foreach (['Supplier product ID', 'Supplier Product ID', 'Style no', 'Sku Brand'] as $sk) {
                    if (array_key_exists($sk, $row) && trim((string)($row[$sk] ?? '')) === '' && $styleNo !== '') {
                        $row[$sk] = $styleNo;
                    }
                }
            }

            if ($supplierCode === 'bobo_choses') {
                // Always fill brand when template brand is empty.
                // Must match UI/label expectation exactly: "Bobo Choses".
                $brandTargetKeys = [
                    'Brend  ', // template column name in current app
                    'Brend',   // just in case spaces trimmed
                    'Brend (manufacturer)',
                    'PRODUCENT',
                ];
                foreach ($brandTargetKeys as $k) {
                    if (!array_key_exists($k, $row)) continue;
                    $current = trim((string)($row[$k] ?? ''));
                    $isEmpty = $current === '';
                    $isJustB = mb_strtoupper($current) === 'B';
                    if ($isEmpty || $isJustB) {
                        $row[$k] = 'Bobo Choses';
                    }
                }
            }

            $outputRows[$idx] = $row;
        }

        return $outputRows;
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array<int,array<string,string>>
     */
    private function applyDeterministicSkuGeneration(string $supplierCode, array $rows, bool $liewoodRetail = false): array
    {
        if ($supplierCode !== 'liewood') {
            return $rows;
        }

        $required = (array) config('product_import.validation.required_columns', []);
        $skuColumn = $liewoodRetail
            ? (string) config('liewood_retail.validation.sku', 'SKU')
            : (string)($required['sku'] ?? 'Sku');

        $setting = BrandSkuSetting::query()->where('supplier_code', $supplierCode)->first();
        $mode = $setting?->mode ?? 'file_then_formula';
        $sizePriority = $setting?->size_column_priority;

        foreach ($rows as $idx => $row) {
            $existing = trim((string) ($row[$skuColumn] ?? ''));

            if ($mode === 'file_only') {
                continue;
            }

            if ($mode === 'file_then_formula' && $existing !== '') {
                continue;
            }

            if ($mode === 'always_formula') {
                $row[$skuColumn] = '';
            }

            $rows[$idx] = $this->liewoodSkuGenerator->fillSkuIfMissing($row, $skuColumn, is_array($sizePriority) ? $sizePriority : null);
        }

        return $rows;
    }
}
