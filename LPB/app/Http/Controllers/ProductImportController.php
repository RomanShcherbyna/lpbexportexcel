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
use App\Services\Export\TemplateLoader;
use App\Services\Supplier\SupplierMappingLoader;
use App\Services\Supplier\SupplierRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProductImportController extends Controller
{
    public function index(SupplierRegistry $registry)
    {
        return view('imports.index', [
            'suppliers' => $registry->listSuppliers(),
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
    )
    {
        $validated = $request->validate([
            'supplier' => ['required', 'string'],
            'file' => ['required', 'file'],
            'export_xlsx' => ['nullable', 'boolean'],
            'export_csv' => ['nullable', 'boolean'],
            'liewood_photo_csv_links' => ['required', 'string', Rule::in(['preview', 'download'])],
        ]);

        $supplier = (string) $validated['supplier'];
        if (!$registry->exists($supplier)) {
            return back()->withErrors(['supplier' => 'Unknown supplier.'])->withInput();
        }

        $exportXlsx = (bool)($request->boolean('export_xlsx'));
        $exportCsv = (bool)($request->boolean('export_csv'));
        if (!$exportXlsx && !$exportCsv) {
            return back()->withErrors(['export' => 'Select at least one export format (xlsx/csv).'])->withInput();
        }

        $file = $request->file('file');
        $stored = $file->store('imports_input');

        $inputPath = Storage::path($stored);
        $originalName = $file->getClientOriginalName();

        $templatePath = (string) config('product_import.template_path');
        if (!file_exists($templatePath)) {
            return back()->withErrors([
                'template' => 'Master template is missing. Place it at: ' . $templatePath,
            ])->withInput();
        }

        try {
            $templateColumns = $templateLoader->loadTemplateColumns($templatePath);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'template' => 'Cannot read master template. Ensure it is a valid .xlsx at: ' . $templatePath,
            ])->withInput();
        }

        $supplierCfg = $supplierMappingLoader->load($supplier);
        $parsed = $parser->parse($inputPath, $supplierCfg['sheet'] ?? null);
        $supplierHeaders = array_values(array_filter($parsed['headers'], fn ($h) => trim((string)$h) !== ''));

        $normalized = $normalizer->normalizeRows($parsed['rows']);
        $sampleRows = array_slice($normalized, 0, 10);

        // Saved mapping override (if exists)
        $savedMapping = $this->loadSavedMappingOverride($supplier);

        // Fallback saved mapping to config mapping if no override file
        $configMapping = is_array($supplierCfg['column_mapping'] ?? null) ? $supplierCfg['column_mapping'] : [];
        $baseSavedMapping = $savedMapping !== null ? $savedMapping : $configMapping;

        // AI suggestion (best-effort; never mandatory)
        $aiUnavailable = null;
        $aiSuggestions = [];
        $aiRaw = null;

        $aiResp = $aiService->suggest($supplierHeaders, $sampleRows, $templateColumns);
        if ($aiResp['ok'] && is_string($aiResp['content'])) {
            $aiRaw = $aiResp['content'];
            $parsedAi = $aiParser->parse($aiRaw, $templateColumns);
            if ($parsedAi['ok']) {
                $aiSuggestions = $parsedAi['suggestions'];
            } else {
                $aiUnavailable = $parsedAi['error'] ?? 'AI response parse error.';
            }
        } else {
            $aiUnavailable = $aiResp['error'] ?? 'AI unavailable.';
        }

        return view('imports.mapping', [
            'input' => [
                'stored_path' => $stored,
                'original_filename' => $originalName,
                'supplier_code' => $supplierCfg['supplier_code'],
                'supplier_name' => $supplierCfg['supplier_name'],
                'export_xlsx' => $exportXlsx,
                'export_csv' => $exportCsv,
                'liewood_photo_csv_links' => (string) $validated['liewood_photo_csv_links'],
            ],
            'template_columns' => $templateColumns,
            'supplier_headers' => $supplierHeaders,
            'saved_mapping' => $baseSavedMapping,
            'ai_suggestions' => array_map(fn ($s) => $s->toArray(), $aiSuggestions),
            'ai_unavailable' => $aiUnavailable,
            'ai_raw_example' => $aiRaw, // for debug visibility; can be hidden later
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
    ) {
        $validated = $request->validate([
            'supplier' => ['required', 'string'],
            'stored_path' => ['required', 'string'],
            'original_filename' => ['required', 'string'],
            'export_xlsx' => ['required', 'boolean'],
            'export_csv' => ['required', 'boolean'],
            'mapping' => ['nullable', 'array'],
            'liewood_photo_csv_links' => ['required', 'string', Rule::in(['preview', 'download'])],
        ]);

        $supplier = (string) $validated['supplier'];
        if (!$registry->exists($supplier)) {
            return back()->withErrors(['supplier' => 'Unknown supplier.'])->withInput();
        }

        $stored = (string) $validated['stored_path'];
        $inputPath = Storage::path($stored);

        $templatePath = (string) config('product_import.template_path');
        if (!file_exists($templatePath)) {
            return back()->withErrors([
                'template' => 'Master template is missing. Place it at: ' . $templatePath,
            ])->withInput();
        }

        $templateColumns = app(TemplateLoader::class)->loadTemplateColumns($templatePath);

        // For resolution we need supplier headers. Re-parse headers only (cheap enough Phase 2).
        $supplierCfg = app(SupplierMappingLoader::class)->load($supplier);
        $parsed = app(ExcelParser::class)->parse($inputPath, $supplierCfg['sheet'] ?? null);
        $supplierHeaders = array_values(array_filter($parsed['headers'], fn ($h) => trim((string)$h) !== ''));

        $savedOverride = $this->loadSavedMappingOverride($supplier);
        $configMapping = is_array($supplierCfg['column_mapping'] ?? null) ? $supplierCfg['column_mapping'] : [];
        $savedMapping = $savedOverride !== null ? $savedOverride : $configMapping;

        // Manual selection from UI: source => template or __unmapped__
        $manualSelection = [];
        foreach ((array)($validated['mapping'] ?? []) as $src => $dst) {
            $manualSelection[(string)$src] = is_string($dst) ? $dst : null;
        }

        // AI is not used here unless user selected it in dropdown; final mapping comes from dropdown values.
        $resolution = $resolver->resolve(
            supplierHeaders: $supplierHeaders,
            savedMapping: $savedMapping,
            aiSuggestions: [], // not applied automatically
            manualSelection: $manualSelection,
            templateColumns: $templateColumns,
        );

        $finalMapping = $resolution['final_mapping'];

        // Persist confirmed mapping override
        $this->saveMappingOverride($supplier, $finalMapping);

        $exportXlsx = (bool)$validated['export_xlsx'];
        $exportCsv = (bool)$validated['export_csv'];

        // Build mapped rows quickly (Phase 3: do not export yet).
        $templateColumns = app(TemplateLoader::class)->loadTemplateColumns((string)config('product_import.template_path'));
        $normalizedRows = app(RowNormalizer::class)->normalizeRows($parsed['rows']);
        $mapped = app(\App\Services\Import\RowMapper::class)->map($normalizedRows, $finalMapping, $templateColumns);
        $mappedRows = $mapped['output_rows'];

        // Auto-apply saved normalization rules (if exist) for preview/export later.
        $savedNormRules = $rulesRepo->loadForSupplier($supplier);
        $savedRulesApplied = $savedNormRules !== [];
        if ($savedRulesApplied) {
            $applied = $ruleApplier->apply($mappedRows, $savedNormRules);
            $mappedRows = $applied['rows'];
        }

        // AI normalization suggestions (Phase 4: safe columns only)
        $allowedTargets = (array)config('product_import.normalization_safe_columns', [
            'Gender',
            'Season',
            'Color Description EN',
            'Color Description PL',
            'Age Size',
        ]);
        $distinctSummary = $prep->buildDistinctValueSummary($mappedRows, $allowedTargets);

        $aiUnavailable = null;
        $rules = [];

        // Safety: AI may not invent values not present in dataset.
        // We will validate canonical values against dataset distinct values per column.
        $aiResp = $aiNormService->suggest($distinctSummary, $allowedTargets);
        if ($aiResp['ok'] && is_string($aiResp['content'])) {
            $parsedRules = $aiNormParser->parse($aiResp['content'], $allowedTargets);
            if ($parsedRules['ok']) {
                $rules = array_map(fn ($r) => $r->toArray(), $parsedRules['rules']);
            } else {
                $aiUnavailable = $parsedRules['error'] ?? 'AI normalization parse error.';
            }
        } else {
            $aiUnavailable = $aiResp['error'] ?? 'AI normalization unavailable.';
        }

        // Enforce "canonical must exist in dataset distinct values" and drop overly broad/unsafe.
        $distinctByCol = [];
        foreach (($distinctSummary['columns'] ?? []) as $col => $meta) {
            $vals = [];
            foreach (($meta['distinct_values'] ?? []) as $pair) {
                $v = trim((string)($pair['value'] ?? ''));
                if ($v !== '') $vals[$v] = true;
            }
            $distinctByCol[$col] = $vals;
        }

        $rules = array_values(array_filter($rules, function ($r) use ($distinctByCol) {
            $col = (string)($r['target_column'] ?? '');
            $canon = trim((string)($r['canonical_value'] ?? ''));
            if ($col === '' || $canon === '') return false;
            if (!isset($distinctByCol[$col][$canon])) {
                return false; // canonical not present in dataset
            }
            $src = $r['source_values'] ?? [];
            if (!is_array($src) || count($src) === 0) return false;
            // also ensure each source exists in dataset
            $set = $distinctByCol[$col];
            foreach ($src as $sv) {
                $sv = trim((string)$sv);
                if ($sv === '' || !isset($set[$sv])) return false;
            }
            // reject broad rules
            return count($src) <= 20;
        }));

        return view('imports.normalization', [
            'input' => [
                'stored_path' => $stored,
                'original_filename' => (string)$validated['original_filename'],
                'supplier_code' => $supplier,
                'supplier_name' => $supplierCfg['supplier_name'],
                'export_xlsx' => $exportXlsx,
                'export_csv' => $exportCsv,
                'mapping' => $finalMapping,
                'liewood_photo_csv_links' => (string) $validated['liewood_photo_csv_links'],
            ],
            'rules' => $rules,
            'ai_unavailable' => $aiUnavailable,
            'saved_rules_applied' => $savedRulesApplied,
        ]);
    }

    public function finalize(
        Request $request,
        SupplierRegistry $registry,
        ConversionPipeline $pipeline,
        NormalizationRulesRepository $rulesRepo,
    ) {
        $validated = $request->validate([
            'supplier' => ['required', 'string'],
            'stored_path' => ['required', 'string'],
            'original_filename' => ['required', 'string'],
            'export_xlsx' => ['required', 'boolean'],
            'export_csv' => ['required', 'boolean'],
            'mapping' => ['required', 'array'],
            'rules' => ['nullable', 'array'],
            'liewood_photo_csv_links' => ['required', 'string', Rule::in(['preview', 'download'])],
        ]);

        $supplier = (string)$validated['supplier'];
        if (!$registry->exists($supplier)) {
            return back()->withErrors(['supplier' => 'Unknown supplier.'])->withInput();
        }

        $stored = (string)$validated['stored_path'];
        $inputPath = Storage::path($stored);

        $templatePath = (string) config('product_import.template_path');
        if (!file_exists($templatePath)) {
            return back()->withErrors([
                'template' => 'Master template is missing. Place it at: ' . $templatePath,
            ])->withInput();
        }

        $finalMapping = [];
        foreach ((array)$validated['mapping'] as $src => $dst) {
            $src = trim((string)$src);
            $dst = trim((string)$dst);
            if ($src !== '' && $dst !== '' && $dst !== '__unmapped__') {
                $finalMapping[$src] = $dst;
            }
        }

        // Build confirmed normalization rules (Phase 4): rule-based, requires explicit apply.
        // Safety:
        // - only safe columns (allowlist)
        // - canonical must be non-empty
        // - canonical must exist in dataset (enforced earlier in UI/AI parser; we still sanitize here)
        $safeColumns = (array)config('product_import.normalization_safe_columns', [
            'Gender',
            'Season',
            'Color Description EN',
            'Color Description PL',
            'Age Size',
        ]);
        $safeSet = array_fill_keys($safeColumns, true);

        $rulesByTarget = [];
        foreach ((array)($validated['rules'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $apply = (bool)($r['apply'] ?? false);
            if (!$apply) continue;

            $target = trim((string)($r['target_column'] ?? ''));
            if ($target === '' || !isset($safeSet[$target])) continue;

            $canon = trim((string)($r['canonical_value'] ?? ''));
            if ($canon === '') continue;

            $srcVals = $r['source_values'] ?? [];
            if (!is_array($srcVals) || count($srcVals) === 0) continue;

            foreach ($srcVals as $sv) {
                $svKey = mb_strtolower(trim((string)$sv));
                $svKey = preg_replace('/\s+/u', ' ', $svKey) ?? $svKey;
                if ($svKey === '') continue;
                $rulesByTarget[$target][$svKey] = $canon;
            }
        }

        if ($rulesByTarget !== []) {
            $rulesRepo->saveForSupplier($supplier, $rulesByTarget);
        }

        $exportXlsx = (bool)$validated['export_xlsx'];
        $exportCsv = (bool)$validated['export_csv'];

        $liewoodGcsUseDownloadProxy = $validated['liewood_photo_csv_links'] === 'download';

        $result = $pipeline->run(
            supplierCode: $supplier,
            inputPath: $inputPath,
            originalFilename: (string)$validated['original_filename'],
            exportXlsx: $exportXlsx,
            exportCsv: $exportCsv,
            overrideColumnMapping: $finalMapping,
            liewoodGcsUseDownloadProxy: $liewoodGcsUseDownloadProxy,
        );

        return view('imports.result', [
            'preview' => $result['preview'],
        ]);
    }

    public function downloadXlsx(string $job)
    {
        return $this->downloadFromJob($job, (string)config('product_import.export.xlsx_name', 'output.xlsx'));
    }

    public function downloadCsv(string $job)
    {
        return $this->downloadFromJob($job, (string)config('product_import.export.csv_name', 'output.csv'));
    }

    private function downloadFromJob(string $job, string $filename): StreamedResponse
    {
        $jobDir = rtrim((string)config('product_import.jobs_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $job;
        $path = $jobDir . DIRECTORY_SEPARATOR . $filename;

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
        }, $filename);
    }

    /**
     * @return array<string,string>|null
     */
    private function loadSavedMappingOverride(string $supplierCode): ?array
    {
        $dir = (string) config('product_import.mapping_overrides_dir');
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $supplierCode . '.json';
        if (!file_exists($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if (!is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $out = [];
        foreach ($decoded as $k => $v) {
            $k = trim((string)$k);
            $v = trim((string)$v);
            if ($k === '' || $v === '') {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * @param array<string,string> $mapping
     */
    private function saveMappingOverride(string $supplierCode, array $mapping): void
    {
        $dir = (string) config('product_import.mapping_overrides_dir');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $supplierCode . '.json';
        file_put_contents($path, json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

