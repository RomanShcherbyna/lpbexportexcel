<?php

namespace App\Http\Controllers;

use App\Models\BrandSkuSetting;
use App\Models\SupplierTypeClassification;
use App\Services\Export\TemplateLoader;
use App\Services\Import\ImportInstructionService;
use App\Services\Import\SupplierTypeClassificationResolver;
use App\Services\Supplier\SupplierMappingLoader;
use App\Services\Supplier\SupplierRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BrandSettingsController extends Controller
{
    public function index(SupplierRegistry $registry)
    {
        return view('imports.settings_index', [
            'suppliers' => $registry->listSuppliers(),
        ]);
    }

    public function show(string $supplier, SupplierRegistry $registry, SupplierTypeClassificationResolver $resolver)
    {
        if (! $registry->exists($supplier)) {
            abort(404);
        }

        $path = config_path('suppliers/'.$supplier.'.php');
        $cfg = is_file($path) ? require $path : [];
        $name = is_array($cfg) ? (string) ($cfg['supplier_name'] ?? $supplier) : $supplier;

        $sku = BrandSkuSetting::query()->where('supplier_code', $supplier)->first();

        $defaultSizeCols = [
            'Size Xs-Xl',
            'KG size',
            'Age size',
            'Age Size ',
            'height size',
            'socks size',
            'hatz size',
            'Shoe size',
            'Общая колонка размеров',
            'Height Size',
            'Socks Size',
            'Hats Size',
        ];

        $priorityLines = '';
        if ($sku !== null && is_array($sku->size_column_priority) && $sku->size_column_priority !== []) {
            $priorityLines = implode("\n", $sku->size_column_priority);
        } else {
            $priorityLines = implode("\n", $defaultSizeCols);
        }

        return view('imports.settings_brand', [
            'supplier_code' => $supplier,
            'supplier_name' => $name,
            'classifications' => $resolver->listForSupplier($supplier),
            'sku_mode' => $sku?->mode ?? 'file_then_formula',
            'size_priority_lines' => $priorityLines,
        ]);
    }

    public function update(
        Request $request,
        string $supplier,
        SupplierRegistry $registry,
    ) {
        if (! $registry->exists($supplier)) {
            abort(404);
        }

        $validated = $request->validate([
            'sku_mode' => ['required', 'string', Rule::in(['file_then_formula', 'file_only', 'always_formula'])],
            // Поле скрыто в UI, но оставляем совместимость для ручных/старых POST.
            'size_column_priority' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'bucket' => ['nullable', 'array'],
            'bucket.*' => ['nullable', 'string', Rule::in(['footwear', 'hat', 'socks', 'generic'])],
        ]);

        $updateData = ['mode' => $validated['sku_mode']];
        if (array_key_exists('size_column_priority', $validated)) {
            $lines = trim((string) ($validated['size_column_priority'] ?? ''));
            $priority = $lines === '' ? null : array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $lines) ?: [])));
            $updateData['size_column_priority'] = $priority === [] ? null : $priority;
        }

        BrandSkuSetting::query()->updateOrCreate(['supplier_code' => $supplier], $updateData);

        $buckets = $request->input('bucket', []);
        if (is_array($buckets)) {
            foreach ($buckets as $id => $bucket) {
                $id = (int) $id;
                if ($id < 1) {
                    continue;
                }
                $row = SupplierTypeClassification::query()->find($id);
                if (! $row instanceof SupplierTypeClassification || $row->supplier_code !== $supplier) {
                    continue;
                }
                $bucket = (string) $bucket;
                if ($bucket === 'generic') {
                    $row->route_bucket = 'generic';
                    $row->save();
                } elseif (in_array($bucket, ['footwear', 'hat', 'socks'], true)) {
                    $row->route_bucket = $bucket;
                    $row->save();
                }
            }
        }

        return redirect()
            ->route('settings.brands.show', ['supplier' => $supplier])
            ->with('status', 'Saved.');
    }

    public function downloadInstruction(
        string $supplier,
        SupplierRegistry $registry,
        SupplierMappingLoader $mappingLoader,
        TemplateLoader $templateLoader,
        ImportInstructionService $instructionService,
    ): StreamedResponse {
        if (! $registry->exists($supplier)) {
            abort(404);
        }

        $supplierCfg = $mappingLoader->load($supplier);
        $supplierName = (string) ($supplierCfg['supplier_name'] ?? $supplier);

        $templateColumns = $templateLoader->loadTemplateColumns((string) config('product_import.template_path'));
        $liewoodRetail = $supplier === 'liewood' && (bool) config('liewood_retail.enabled', true);
        if ($liewoodRetail) {
            $retailCols = config('liewood_retail.template_column_keys');
            if (is_array($retailCols) && $retailCols !== []) {
                $templateColumns = array_values($retailCols);
            }
        }

        $mapping = is_array($supplierCfg['column_mapping'] ?? null) ? $supplierCfg['column_mapping'] : [];
        $mappingStatus = [];
        foreach ($mapping as $src => $dst) {
            $src = trim((string) $src);
            $dst = trim((string) $dst);
            if ($src === '' || $dst === '') {
                continue;
            }
            $mappingStatus[] = [
                'source' => $src,
                'target' => $dst,
                'status' => 'mapped_exact',
            ];
        }

        $passport = $instructionService->buildPassport(
            $supplier,
            $supplierName,
            $templateColumns,
            $mappingStatus,
            [
                'liewood_retail' => $liewoodRetail,
                'source' => 'brand_default_config',
                'example_row' => $this->latestExampleRowForSupplier($supplier),
            ],
        );
        $rows = $instructionService->buildInstructionRows($passport);

        $tmpPath = tempnam(sys_get_temp_dir(), 'lpb_instr_');
        if ($tmpPath === false) {
            abort(500, 'Cannot create temp file');
        }
        $instructionService->writeInstructionCsv($rows, $tmpPath);

        $filename = sprintf('instruction-%s.csv', $supplier);

        return response()->streamDownload(function () use ($tmpPath): void {
            $fh = fopen($tmpPath, 'rb');
            if ($fh !== false) {
                while (! feof($fh)) {
                    echo fread($fh, 1024 * 1024);
                }
                fclose($fh);
            }
            @unlink($tmpPath);
        }, $filename);
    }

    /**
     * Берем первую строку данных из последнего preview.json этого бренда.
     *
     * @return array<string, string>
     */
    private function latestExampleRowForSupplier(string $supplier): array
    {
        $jobsDir = rtrim((string) config('product_import.jobs_dir'), DIRECTORY_SEPARATOR);
        if ($jobsDir === '' || ! is_dir($jobsDir)) {
            return [];
        }

        $latestMTime = -1;
        $latestRow = [];
        foreach (glob($jobsDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'preview.json') ?: [] as $previewPath) {
            $json = @file_get_contents($previewPath);
            if (! is_string($json) || $json === '') {
                continue;
            }
            $decoded = json_decode($json, true);
            if (! is_array($decoded)) {
                continue;
            }
            $code = (string) ($decoded['input']['supplier_code'] ?? '');
            if ($code !== $supplier) {
                continue;
            }
            $rows = $decoded['preview_rows'] ?? null;
            if (! is_array($rows) || ! isset($rows[0]) || ! is_array($rows[0])) {
                continue;
            }
            $mtime = (int) (@filemtime($previewPath) ?: 0);
            if ($mtime >= $latestMTime) {
                $latestMTime = $mtime;
                /** @var array<string, string> $candidate */
                $candidate = [];
                foreach ($rows[0] as $k => $v) {
                    $candidate[(string) $k] = (string) $v;
                }
                $latestRow = $candidate;
            }
        }

        return $latestRow;
    }
}
