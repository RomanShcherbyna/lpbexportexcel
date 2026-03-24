<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiMappingSuggestionParser;
use App\Services\Ai\AiMappingSuggestionService;
use App\Services\Ai\AiNormalizationSuggestionParser;
use App\Services\Ai\AiNormalizationSuggestionService;
use App\Services\Import\ConversionPipeline;
use App\Services\Import\ExcelParser;
use App\Services\Import\AiNormalizationPreparationService;
use App\Services\Import\ConfirmedRuleApplier;
use App\Services\Import\MappingResolutionService;
use App\Services\Import\NormalizationRulesRepository;
use App\Services\Import\RowNormalizer;
use App\Services\Import\SupplierTypeClassificationResolver;
use App\Services\Export\TemplateLoader;
use App\Services\Supplier\SupplierMappingLoader;
use App\Services\Supplier\SupplierRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProductImportController extends Controller
{
    private const GCS_NO_PHOTO = '__NO_PHOTO__';

    public function index(SupplierRegistry $registry)
    {
        $defaultPrefix = $this->normalizeLiewoodGcsFolderPrefix((string) config('liewood_drive.gcs_prefix', ''));
        $oldPrefix = old('liewood_gcs_prefix');
        $selectedDefault = ($oldPrefix !== null && (string) $oldPrefix !== '')
            ? $this->normalizeLiewoodGcsFolderPrefix((string) $oldPrefix)
            : $defaultPrefix;

        $prefixOptions = $this->discoverGcsPrefixes();
        $prefixOptions = array_values(array_filter($prefixOptions, fn (string $p): bool => ! $this->isGcsImagesSubfolderPrefix($p)));
        if ($selectedDefault !== '' && ! in_array($selectedDefault, $prefixOptions, true)) {
            array_unshift($prefixOptions, $selectedDefault);
        }
        if ($prefixOptions === []) {
            $prefixOptions = [$selectedDefault !== '' ? $selectedDefault : ''];
        }
        if (! in_array(self::GCS_NO_PHOTO, $prefixOptions, true)) {
            array_unshift($prefixOptions, self::GCS_NO_PHOTO);
        }

        return view('imports.index', [
            'suppliers' => $registry->listSuppliers(),
            'gcs_prefix_options' => $prefixOptions,
            'gcs_prefix_default' => $selectedDefault,
            'gcs_no_photo_value' => self::GCS_NO_PHOTO,
        ]);
    }

    public function mapping(
        Request $request,
        SupplierRegistry $registry,
        SupplierMappingLoader $supplierMappingLoader,
        TemplateLoader $templateLoader,
        ExcelParser $parser,
        RowNormalizer $normalizer,
        AiMappingSuggestionService $aiService,
        AiMappingSuggestionParser $aiParser,
        SupplierTypeClassificationResolver $typeClassificationResolver,
    ) {
        $validated = $request->validate([
            'supplier' => ['required', 'string'],
            'file' => ['required', 'file'],
            'export_xlsx' => ['nullable', 'boolean'],
            'export_csv' => ['nullable', 'boolean'],
            'liewood_photo_csv_links' => ['required', 'string', Rule::in(['preview', 'download'])],
            'liewood_gcs_prefix' => ['required', 'string', 'max:255'],
        ]);

        $supplier = (string) $validated['supplier'];
        if (!$registry->exists($supplier)) {
            return back()->withErrors(['supplier' => 'Unknown supplier.'])->withInput();
        }

        // Force CSV-only export for now.
        $exportXlsx = false;
        $exportCsv = true;

        $file = $request->file('file');
        $stored = $file->store('imports_input');

        $inputPath = Storage::path($stored);
        $originalName = $file->getClientOriginalName();

        // Load canonical template columns from config (or legacy Excel template as fallback).
        $templateColumns = $templateLoader->loadTemplateColumns((string) config('product_import.template_path'));

        $supplierCfg = $supplierMappingLoader->load($supplier);
        $parsed = $parser->parse($inputPath, $supplierCfg['sheet'] ?? null);
        $supplierHeaders = array_values(array_filter($parsed['headers'], fn ($h) => trim((string)$h) !== ''));

        $normalized = $normalizer->normalizeRows($parsed['rows']);
        $sampleRows = array_slice($normalized, 0, 10);

        $configMapping = is_array($supplierCfg['column_mapping'] ?? null) ? $supplierCfg['column_mapping'] : [];
        $configMapping = $this->resolveConfiguredMappingForHeaders($configMapping, $supplierHeaders);
        // Always start from fresh config mapping for UI; no persistent cache.
        $baseSavedMapping = $configMapping;

        $unknownLiewoodTypes = [];
        if ($supplier === 'liewood') {
            $unknownLiewoodTypes = $this->unknownLiewoodTypesFromNormalized(
                $typeClassificationResolver,
                $baseSavedMapping,
                $normalized
            );
        }

        // AI suggestions are disabled for now; keep UI but show clear message.
        $aiUnavailable = 'AI mapping suggestions are disabled.';
        $aiSuggestions = [];
        $aiRaw = null;

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H1',
                'location' => 'app/Http/Controllers/ProductImportController.php:mapping',
                'message' => 'Reached mapping view render',
                'data' => [
                    'supplier' => $supplier,
                    'headers_count' => count($supplierHeaders),
                    'template_columns_count' => count($templateColumns),
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

        return view('imports.mapping', [
            'input' => [
                'stored_path' => $stored,
                'original_filename' => $originalName,
                'supplier_code' => $supplierCfg['supplier_code'],
                'supplier_name' => $supplierCfg['supplier_name'],
                'export_xlsx' => $exportXlsx,
                'export_csv' => $exportCsv,
                'liewood_photo_csv_links' => (string) $validated['liewood_photo_csv_links'],
                'liewood_gcs_prefix' => $this->normalizeLiewoodGcsFolderPrefix((string) $validated['liewood_gcs_prefix']),
            ],
            'template_columns' => $templateColumns,
            'supplier_headers' => $supplierHeaders,
            'saved_mapping' => $baseSavedMapping,
            'ai_suggestions' => array_map(fn ($s) => $s->toArray(), $aiSuggestions),
            'ai_unavailable' => $aiUnavailable,
            'ai_raw_example' => $aiRaw,
            'unknown_liewood_types' => $unknownLiewoodTypes,
        ]);
    }

    public function normalize(
        Request $request,
        SupplierRegistry $registry,
        MappingResolutionService $resolver,
        AiNormalizationPreparationService $prep,
        AiNormalizationSuggestionService $aiNormService,
        AiNormalizationSuggestionParser $aiNormParser,
        NormalizationRulesRepository $rulesRepo,
        ConfirmedRuleApplier $ruleApplier,
        SupplierTypeClassificationResolver $typeClassificationResolver,
    ) {
        $validated = $request->validate([
            'supplier' => ['required', 'string'],
            'stored_path' => ['required', 'string'],
            'original_filename' => ['required', 'string'],
            'export_xlsx' => ['required', 'boolean'],
            'export_csv' => ['required', 'boolean'],
            'mapping' => ['nullable', 'array'],
            'liewood_photo_csv_links' => ['required', 'string', Rule::in(['preview', 'download'])],
            'liewood_gcs_prefix' => ['required', 'string', 'max:255'],
        ]);

        $supplier = (string) $validated['supplier'];
        if (!$registry->exists($supplier)) {
            return back()->withErrors(['supplier' => 'Unknown supplier.'])->withInput();
        }

        $stored = (string) $validated['stored_path'];
        $inputPath = Storage::path($stored);

        // Load canonical template columns from config (or legacy Excel template as fallback).
        $templateColumns = app(TemplateLoader::class)->loadTemplateColumns((string) config('product_import.template_path'));

        $supplierCfg = app(SupplierMappingLoader::class)->load($supplier);
        $parsed = app(ExcelParser::class)->parse($inputPath, $supplierCfg['sheet'] ?? null);
        $supplierHeaders = array_values(array_filter($parsed['headers'], fn ($h) => trim((string)$h) !== ''));

        $configMapping = is_array($supplierCfg['column_mapping'] ?? null) ? $supplierCfg['column_mapping'] : [];
        $configMapping = $this->resolveConfiguredMappingForHeaders($configMapping, $supplierHeaders);
        // Do not use any persisted overrides; always resolve from config + current manual choices.
        $savedMapping = $configMapping;

        $manualSelection = [];
        foreach ((array)($validated['mapping'] ?? []) as $src => $dst) {
            $manualSelection[(string)$src] = is_string($dst) ? $dst : null;
        }

        $resolution = $resolver->resolve(
            supplierHeaders: $supplierHeaders,
            savedMapping: $savedMapping,
            aiSuggestions: [],
            manualSelection: $manualSelection,
            templateColumns: $templateColumns,
        );

        $finalMapping = $resolution['final_mapping'];

        $normalizedRows = app(RowNormalizer::class)->normalizeRows($parsed['rows']);

        if ($supplier === 'liewood') {
            $unknown = $this->unknownLiewoodTypesFromNormalized(
                $typeClassificationResolver,
                $finalMapping,
                $normalizedRows
            );
            if ($unknown !== []) {
                $hashToRaw = $request->input('unknown_type_hash', []);
                $typeRes = $request->input('type_resolution', []);
                if (! is_array($hashToRaw)) {
                    $hashToRaw = [];
                }
                if (! is_array($typeRes)) {
                    $typeRes = [];
                }
                $toSave = [];
                foreach ($unknown as $raw) {
                    $h = md5($raw);
                    if (($hashToRaw[$h] ?? null) !== $raw) {
                        return back()->withErrors(['type_resolution' => 'Invalid type classification payload.'])->withInput();
                    }
                    $bucket = $typeRes[$h] ?? null;
                    if (! in_array($bucket, ['footwear', 'hat', 'socks', 'generic'], true)) {
                        return back()->withErrors([
                            'type_resolution' => 'Classify every unknown Type value (see block above the mapping table). Missing: '.$raw,
                        ])->withInput();
                    }
                    $toSave[$raw] = $bucket;
                }
                $typeClassificationResolver->saveResolutions($supplier, $toSave);
            }
        }

        $exportXlsx = (bool)$validated['export_xlsx'];
        $exportCsv = (bool)$validated['export_csv'];

        $templateColumns = app(TemplateLoader::class)->loadTemplateColumns((string)config('product_import.template_path'));
        $mapped = app(\App\Services\Import\RowMapper::class)->map($normalizedRows, $finalMapping, $templateColumns);
        $mappedRows = $mapped['output_rows'];

        $savedNormRules = $rulesRepo->loadForSupplier($supplier);
        $savedRulesApplied = $savedNormRules !== [];
        if ($savedRulesApplied) {
            $applied = $ruleApplier->apply($mappedRows, $savedNormRules);
            $mappedRows = $applied['rows'];
        }

        $allowedTargets = (array)config('product_import.normalization_safe_columns', [
            'Gender',
            'Season',
            'Color Description EN',
            'Color Description PL',
            'Age Size',
        ]);
        $distinctSummary = $prep->buildDistinctValueSummary($mappedRows, $allowedTargets);

        // AI normalization suggestions are disabled for now.
        $aiUnavailable = 'AI normalization suggestions are disabled.';
        $rules = [];

        $distinctByCol = [];
        foreach (($distinctSummary['columns'] ?? []) as $col => $meta) {
            $vals = [];
            foreach (($meta['distinct_values'] ?? []) as $pair) {
                $v = trim((string)($pair['value'] ?? ''));
                if ($v !== '') {
                    $vals[$v] = true;
                }
            }
            $distinctByCol[$col] = $vals;
        }

        $rules = array_values(array_filter($rules, function ($r) use ($distinctByCol) {
            $col = (string)($r['target_column'] ?? '');
            $canon = trim((string)($r['canonical_value'] ?? ''));
            if ($col === '' || $canon === '') {
                return false;
            }
            if (!isset($distinctByCol[$col][$canon])) {
                return false;
            }
            $src = $r['source_values'] ?? [];
            if (!is_array($src) || count($src) === 0) {
                return false;
            }
            return true;
        }));

        return view('imports.normalization', [
            'input' => [
                'stored_path' => $stored,
                'original_filename' => $validated['original_filename'],
                'supplier_code' => $supplier,
                'supplier_name' => $supplierCfg['supplier_name'] ?? $supplier,
                'export_xlsx' => $exportXlsx,
                'export_csv' => $exportCsv,
                'mapping' => $finalMapping,
                'liewood_photo_csv_links' => (string) $validated['liewood_photo_csv_links'],
                'liewood_gcs_prefix' => $this->normalizeLiewoodGcsFolderPrefix((string) $validated['liewood_gcs_prefix']),
            ],
            'rules' => $rules,
            'ai_unavailable' => $aiUnavailable,
            'saved_rules_applied' => $savedRulesApplied,
        ]);
    }

    public function finalize(
        Request $request,
        SupplierRegistry $registry,
        SupplierMappingLoader $supplierMappingLoader,
        ConversionPipeline $pipeline,
    ) {
        $validated = $request->validate([
            'supplier' => ['required', 'string'],
            'stored_path' => ['required', 'string'],
            'original_filename' => ['required', 'string'],
            'export_xlsx' => ['required', 'boolean'],
            'export_csv' => ['required', 'boolean'],
            'mapping' => ['nullable', 'array'],
            'rules' => ['nullable', 'array'],
            'liewood_photo_csv_links' => ['required', 'string', Rule::in(['preview', 'download'])],
            'liewood_gcs_prefix' => ['required', 'string', 'max:255'],
        ]);

        $supplier = (string) $validated['supplier'];
        if (!$registry->exists($supplier)) {
            return back()->withErrors(['supplier' => 'Unknown supplier.'])->withInput();
        }

        $stored = (string) $validated['stored_path'];
        $inputPath = Storage::path($stored);

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H2',
                'location' => 'app/Http/Controllers/ProductImportController.php:finalize:beforeRun',
                'message' => 'About to run ConversionPipeline',
                'data' => [
                    'supplier' => $supplier,
                    'stored' => $stored,
                    'export_xlsx' => (bool)$validated['export_xlsx'],
                    'export_csv' => (bool)$validated['export_csv'],
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

        $supplierCfg = $supplierMappingLoader->load($supplier);
        $configMapping = is_array($supplierCfg['column_mapping'] ?? null) ? $supplierCfg['column_mapping'] : [];
        $manualMapping = (array)($validated['mapping'] ?? []);
        $templateColumns = app(TemplateLoader::class)->loadTemplateColumns((string)config('product_import.template_path'));
        $manualMapping = $this->canonicalizeMappingTargets($manualMapping, $templateColumns);

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'debug-brand-sku',
                'hypothesisId' => 'H1',
                'location' => 'app/Http/Controllers/ProductImportController.php:finalize',
                'message' => 'Finalize mapping inputs',
                'data' => [
                    'supplier' => $supplier,
                    'request_mapping_brand' => $request->input('mapping.Brand'),
                    'validated_mapping_brand' => $manualMapping['Brand'] ?? null,
                    'config_mapping_brand' => $configMapping['Brand'] ?? null,
                    'config_mapping_style_no' => $configMapping['Style No'] ?? null,
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

        // Always keep supplier default mapping; manual selections can only override existing keys.
        $effectiveMapping = array_merge($configMapping, $manualMapping);

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'debug-brand-sku',
                'hypothesisId' => 'H2',
                'location' => 'app/Http/Controllers/ProductImportController.php:finalize',
                'message' => 'Effective mapping after merge',
                'data' => [
                    'effective_mapping_brand' => $effectiveMapping['Brand'] ?? null,
                    'effective_mapping_style_no' => $effectiveMapping['Style No'] ?? null,
                    'effective_mapping_count' => count($effectiveMapping),
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

        $liewoodGcsUseDownloadProxy = $validated['liewood_photo_csv_links'] === 'download';
        $liewoodGcsPrefix = $this->normalizeLiewoodGcsFolderPrefix((string) $validated['liewood_gcs_prefix']);

        $job = $pipeline->run(
            $supplier,
            $inputPath,
            (string)$validated['original_filename'],
            (bool)$validated['export_xlsx'],
            (bool)$validated['export_csv'],
            $effectiveMapping,
            $liewoodGcsUseDownloadProxy,
            $liewoodGcsPrefix,
        );

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H2',
                'location' => 'app/Http/Controllers/ProductImportController.php:finalize:afterRun',
                'message' => 'ConversionPipeline finished',
                'data' => [
                    'job_id' => $job['job_id'] ?? null,
                    'has_preview' => array_key_exists('preview', $job ?? []),
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

        return redirect()->route('imports.products.download.csv', ['job' => $job['job_id']]);
    }

    public function downloadXlsx(string $job, ConversionPipeline $pipeline): StreamedResponse
    {
        $jobDir = rtrim((string)config('product_import.jobs_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $job;
        $path = $jobDir . DIRECTORY_SEPARATOR . (string)config('product_import.export.xlsx_name', 'output.xlsx');

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->streamDownload(function () use ($path) {
            $fh = fopen($path, 'rb');
            if ($fh === false) {
                return;
            }
            while (!feof($fh)) {
                echo fread($fh, 1024 * 1024);
            }
            fclose($fh);
        }, basename($path));
    }

    public function downloadCsv(string $job, ConversionPipeline $pipeline): StreamedResponse
    {
        $jobDir = rtrim((string)config('product_import.jobs_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $job;
        $path = $jobDir . DIRECTORY_SEPARATOR . (string)config('product_import.export.csv_name', 'output.csv');

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->streamDownload(function () use ($path) {
            $fh = fopen($path, 'rb');
            if ($fh === false) {
                return;
            }
            while (!feof($fh)) {
                echo fread($fh, 1024 * 1024);
            }
            fclose($fh);
        }, basename($path));
    }

    public function downloadInstructionCsv(string $job): StreamedResponse
    {
        $jobDir = rtrim((string) config('product_import.jobs_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $job;
        $path = $jobDir . DIRECTORY_SEPARATOR . (string) config('product_import.export.instruction_csv_name', 'instruction.csv');

        if (! file_exists($path)) {
            abort(404);
        }

        return response()->streamDownload(function () use ($path): void {
            $fh = fopen($path, 'rb');
            if ($fh === false) {
                return;
            }
            while (! feof($fh)) {
                echo fread($fh, 1024 * 1024);
            }
            fclose($fh);
        }, basename($path));
    }

    /**
     * Колонка шаблона, куда маппится Liewood Type (retail vs legacy master).
     */
    private function liewoodTypeTargetTemplateColumn(): string
    {
        $isRetail = filter_var(env('LIEWOOD_RETAIL_EXPORT', true), FILTER_VALIDATE_BOOLEAN);

        return $isRetail ? 'item SubCategory' : 'item_category';
    }

    /**
     * Исходные заголовки файла, из которых берётся Type.
     *
     * @param  array<string, string>  $resolvedSourceToTemplate
     * @return list<string>
     */
    private function liewoodTypeSourceColumns(array $resolvedSourceToTemplate): array
    {
        $target = $this->liewoodTypeTargetTemplateColumn();
        $cols = [];
        foreach ($resolvedSourceToTemplate as $src => $dst) {
            if ($dst === $target) {
                $cols[] = $src;
            }
        }

        return $cols;
    }

    /**
     * Значения Type из файла, которых нет в конфиге liewood_retail и не сохранено в БД.
     *
     * @param  array<string, string>  $resolvedMapping
     * @param  array<int, array<string, string>>  $normalizedRows
     * @return list<string>
     */
    private function unknownLiewoodTypesFromNormalized(
        SupplierTypeClassificationResolver $resolver,
        array $resolvedMapping,
        array $normalizedRows,
    ): array {
        $cols = $this->liewoodTypeSourceColumns($resolvedMapping);
        if ($cols === []) {
            return [];
        }

        $distinct = [];
        foreach ($normalizedRows as $row) {
            foreach ($cols as $col) {
                $t = trim((string) ($row[$col] ?? ''));
                if ($t !== '') {
                    $distinct[$t] = true;
                }
            }
        }

        $known = $resolver->knownTypeKeysMap('liewood');
        $unknown = [];
        foreach (array_keys($distinct) as $v) {
            $k = mb_strtoupper((string) $v);
            if (! isset($known[$k])) {
                $unknown[] = (string) $v;
            }
        }
        sort($unknown);

        return $unknown;
    }

    private function loadSavedMappingOverride(string $supplier): ?array
    {
        // Persistent mapping cache is disabled for now: always return null.
        return null;
    }

    private function saveMappingOverride(string $supplier, array $mapping): void
    {
        // Persistent mapping cache is disabled for now: do nothing.
    }

    /**
     * Align configured source headers to actual headers from uploaded file.
     * This prevents drops caused by case/BOM/spacing differences.
     *
     * @param array<string,string> $configMapping
     * @param array<int,string> $supplierHeaders
     * @return array<string,string>
     */
    private function resolveConfiguredMappingForHeaders(array $configMapping, array $supplierHeaders): array
    {
        $exact = [];
        $normalized = [];

        foreach ($configMapping as $src => $dst) {
            $srcKey = trim((string)$src);
            $dstVal = (string)$dst;
            if ($srcKey === '' || trim($dstVal) === '') {
                continue;
            }

            $exact[$srcKey] = $dstVal;

            $norm = $this->normalizeHeaderKey($srcKey);
            if (!isset($normalized[$norm])) {
                $normalized[$norm] = $dstVal;
            }
        }

        $resolved = [];
        foreach ($supplierHeaders as $header) {
            $actual = trim((string)$header);
            if ($actual === '') {
                continue;
            }

            if (isset($exact[$actual])) {
                $resolved[$actual] = $exact[$actual];
                continue;
            }

            $normActual = $this->normalizeHeaderKey($actual);
            if (isset($normalized[$normActual])) {
                $resolved[$actual] = $normalized[$normActual];
            }
        }

        return $resolved;
    }

    private function normalizeHeaderKey(string $value): string
    {
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower($value);
    }

    /**
     * Canonicalize submitted mapping targets to exact template column names.
     * Needed because request middleware may trim values like "Brend  " to "Brend".
     *
     * @param array<string,mixed> $mapping
     * @param array<int,string> $templateColumns
     * @return array<string,string>
     */
    private function canonicalizeMappingTargets(array $mapping, array $templateColumns): array
    {
        $templateSet = array_fill_keys($templateColumns, true);
        $byNormalized = [];
        foreach ($templateColumns as $col) {
            $norm = $this->normalizeHeaderKey((string)$col);
            if (!isset($byNormalized[$norm])) {
                $byNormalized[$norm] = (string)$col;
            }
        }

        $resolved = [];
        foreach ($mapping as $src => $dst) {
            $source = trim((string)$src);
            if ($source === '' || !is_string($dst)) {
                continue;
            }

            if ($dst === '__unmapped__' || trim($dst) === '') {
                continue;
            }

            if (isset($templateSet[$dst])) {
                $resolved[$source] = $dst;
                continue;
            }

            $normalized = $this->normalizeHeaderKey($dst);
            if (isset($byNormalized[$normalized])) {
                $resolved[$source] = $byNormalized[$normalized];
            }
        }

        // #region agent log
        @file_put_contents(
            base_path('.cursor/debug-9a7511.log'),
            json_encode([
                'sessionId' => '9a7511',
                'runId' => 'post-fix',
                'hypothesisId' => 'H1',
                'location' => 'app/Http/Controllers/ProductImportController.php:canonicalizeMappingTargets',
                'message' => 'Manual mapping canonicalized against template columns',
                'data' => [
                    'input_brand' => $mapping['Brand'] ?? null,
                    'resolved_brand' => $resolved['Brand'] ?? null,
                    'input_style_no' => $mapping['Style No'] ?? null,
                    'resolved_style_no' => $resolved['Style No'] ?? null,
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ]) . PHP_EOL,
            FILE_APPEND
        );
        // #endregion

        return $resolved;
    }

    /**
     * Уровень папки сезона в GCS (без суффикса /images/).
     * Объекты под `season/.../images/...` всё равно попадают в list с prefix=season/.
     */
    private function normalizeLiewoodGcsFolderPrefix(string $prefix): string
    {
        $p = ltrim(trim($prefix), '/');
        if ($p === self::GCS_NO_PHOTO) {
            return self::GCS_NO_PHOTO;
        }
        if ($p === '') {
            return '';
        }
        if (preg_match('#(^|/)images/?$#i', $p)) {
            $p = (string) preg_replace('#/images/?$#i', '', $p);
        }
        $p = rtrim($p, '/');

        return $p === '' ? '' : $p.'/';
    }

    private function isGcsImagesSubfolderPrefix(string $prefix): bool
    {
        $p = rtrim(trim($prefix), '/');
        if ($p === '') {
            return false;
        }

        return str_ends_with(strtolower($p), '/images')
            || strtolower(basename(str_replace('\\', '/', $p))) === 'images';
    }

    /**
     * @return list<string>
     */
    private function discoverGcsPrefixes(): array
    {
        $bucket = trim((string) config('liewood_drive.gcs_bucket', ''));
        $rootPrefix = ltrim(trim((string) config('liewood_drive.gcs_prefix_root', '')), '/');
        if ($bucket === '') {
            return [];
        }

        $prefixes = [];
        $pageToken = null;
        $maxPages = 20;

        try {
            for ($page = 0; $page < $maxPages; $page++) {
                $query = [
                    'delimiter' => '/',
                    'maxResults' => 1000,
                ];
                if ($rootPrefix !== '') {
                    $query['prefix'] = $rootPrefix;
                }
                if (is_string($pageToken) && $pageToken !== '') {
                    $query['pageToken'] = $pageToken;
                }

                $url = 'https://storage.googleapis.com/storage/v1/b/'.rawurlencode($bucket).'/o';
                $resp = Http::timeout(10)->get($url, $query);
                if (! $resp->successful()) {
                    break;
                }

                $json = $resp->json();
                if (! is_array($json)) {
                    break;
                }

                foreach ((array) ($json['prefixes'] ?? []) as $p) {
                    $v = trim((string) $p);
                    if ($v !== '') {
                        $prefixes[$v] = true;
                    }
                }

                $pageToken = (string) ($json['nextPageToken'] ?? '');
                if ($pageToken === '') {
                    break;
                }
            }
        } catch (\Throwable) {
            // If GCS listing is unavailable, fallback to configured default prefix.
        }

        $out = array_keys($prefixes);
        $out = array_values(array_filter($out, fn (string $p): bool => ! $this->isGcsImagesSubfolderPrefix($p)));
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }
}

