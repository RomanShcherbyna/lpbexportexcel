<?php

namespace App\Services\Import;

use App\Services\Ai\AiDataCheckerParser;
use App\Services\Ai\AiDataCheckerService;
use App\Services\Ai\AiDatasetSummaryBuilder;
use App\Services\Export\CsvExporter;
use App\Services\Export\TemplateLoader;
use App\Services\Export\XlsxExporter;
use App\Services\Sku\LiewoodSkuGenerator;
use App\Services\Supplier\SupplierMappingLoader;
use App\Services\Import\ConfirmedRuleApplier;
use App\Services\Import\NormalizationRulesRepository;
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
        ?array $overrideColumnMapping = null
    ): array
    {
        $jobId = (string) Str::uuid();
        $jobDir = rtrim((string)config('product_import.jobs_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $jobId;

        if (!is_dir($jobDir)) {
            mkdir($jobDir, 0775, true);
        }

        $templatePath = (string) config('product_import.template_path');
        $templateColumns = $this->templateLoader->loadTemplateColumns($templatePath);

        $supplierCfg = $this->mappingLoader->load($supplierCode);
        $columnMapping = is_array($overrideColumnMapping) ? $overrideColumnMapping : ($supplierCfg['column_mapping'] ?? []);

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'debug-brand-sku',
                'hypothesisId' => 'H3',
                'location' => 'LPB/app/Services/Import/ConversionPipeline.php:run',
                'message' => 'Column mapping entering pipeline',
                'data' => [
                    'supplier_code' => $supplierCode,
                    'brand_target' => $columnMapping['Brand'] ?? null,
                    'style_no_target' => $columnMapping['Style No'] ?? null,
                    'mapping_count' => is_array($columnMapping) ? count($columnMapping) : null,
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

        $parsed = $this->parser->parse($inputPath, $supplierCfg['sheet'] ?? null);
        $supplierHeaders = $parsed['headers'];

        $normalizedRows = $this->normalizer->normalizeRows($parsed['rows']);

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'debug-brand-sku',
                'hypothesisId' => 'H4',
                'location' => 'LPB/app/Services/Import/ConversionPipeline.php:run:beforeMap',
                'message' => 'Template/header presence check before mapping',
                'data' => [
                    'has_template_brend_exact' => in_array('Brend  ', $templateColumns, true),
                    'has_template_brend_trimmed' => in_array('Brend', $templateColumns, true),
                    'has_header_brand' => in_array('Brand', $supplierHeaders, true),
                    'has_header_style_no' => in_array('Style No', $supplierHeaders, true),
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

        $mapped = $this->mapper->map($normalizedRows, $columnMapping, $templateColumns);
        $outputRows = $mapped['output_rows'];
        $outputRows = $this->applyCriticalFallbacks($supplierCode, $normalizedRows, $outputRows);

        // Deterministic SKU generation for suppliers that require it.
        $outputRows = $this->applyDeterministicSkuGeneration($supplierCode, $outputRows);
        $missingSourceColumns = [];
        foreach (($mapped['mapping_status'] ?? []) as $ms) {
            if (($ms['status'] ?? '') === 'missing_source') {
                $missingSourceColumns[] = (string)($ms['source'] ?? '');
            }
        }

        $required = (array) config('product_import.validation.required_columns', []);
        $requiredCols = [
            'sku' => (string)($required['sku'] ?? 'Sku'),
            'name' => (string)($required['name'] ?? 'Product name (EN)'),
            'price' => (string)($required['price'] ?? 'Wholesale Price'),
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

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H3',
                'location' => 'LPB/app/Services/Import/ConversionPipeline.php:validation',
                'message' => 'After validation',
                'data' => [
                    'total_rows_before_validation' => count($outputRows),
                    'rows_valid' => $validation['summary']['rows_valid'] ?? null,
                    'rows_error' => $validation['summary']['rows_error'] ?? null,
                    'first_row_sample' => $outputRows[0] ?? null,
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

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
        ];

        if ($exportXlsx) {
            $xlsxName = (string) config('product_import.export.xlsx_name', 'output.xlsx');
            $xlsxPath = $jobDir . DIRECTORY_SEPARATOR . $xlsxName;
            $this->xlsxExporter->exportToPath($templateColumns, $rowsForExport, $xlsxPath);
            $exportPaths['xlsx'] = $xlsxName;
        }

        if ($exportCsv) {
            $csvName = (string) config('product_import.export.csv_name', 'output.csv');
            $csvPath = $jobDir . DIRECTORY_SEPARATOR . $csvName;
            $this->csvExporter->exportToPath($templateColumns, $rowsForExport, $csvPath);
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
        if ($supplierCode !== 'liewood') {
            return $outputRows;
        }

        foreach ($outputRows as $idx => $row) {
            $src = $sourceRows[$idx] ?? [];

            $brand = trim((string)($src['Brand'] ?? ''));
            if (trim((string)($row['Brend  '] ?? '')) === '' && $brand !== '') {
                $row['Brend  '] = $brand;
            }

            $styleNo = trim((string)($src['Style No'] ?? ''));
            if (trim((string)($row['Supplier Product ID'] ?? '')) === '' && $styleNo !== '') {
                $row['Supplier Product ID'] = $styleNo;
            }

            $outputRows[$idx] = $row;
        }

        return $outputRows;
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array<int,array<string,string>>
     */
    private function applyDeterministicSkuGeneration(string $supplierCode, array $rows): array
    {
        if ($supplierCode !== 'liewood') {
            return $rows;
        }

        $required = (array) config('product_import.validation.required_columns', []);
        $skuColumn = (string)($required['sku'] ?? 'Sku');

        foreach ($rows as $idx => $row) {
            $rows[$idx] = $this->liewoodSkuGenerator->fillSkuIfMissing($row, $skuColumn);
        }

        return $rows;
    }
}
