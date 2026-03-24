<?php

namespace App\Services\Import;

use App\Models\SupplierTypeClassification;

/**
 * Сохранённые привязки Type → bucket (обувь/шапка/носки/эвристика) по бренду.
 */
final class SupplierTypeClassificationResolver
{
    /**
     * Ключи Type (верхний регистр), уже покрытые конфигом liewood_retail или явно сохранённые.
     *
     * @return array<string, true>
     */
    public function knownTypeKeysMap(string $supplierCode): array
    {
        $map = [];
        if ($supplierCode === 'liewood') {
            foreach ((array) config('liewood_retail.footwear_types', []) as $t) {
                $k = mb_strtoupper(trim((string) $t));
                if ($k !== '') {
                    $map[$k] = true;
                }
            }
            foreach ((array) config('liewood_retail.hat_types', []) as $t) {
                $k = mb_strtoupper(trim((string) $t));
                if ($k !== '') {
                    $map[$k] = true;
                }
            }
        }

        return array_merge($map, $this->savedTypeKeysMap($supplierCode));
    }

    /**
     * @return array<string, true>
     */
    public function savedTypeKeysMap(string $supplierCode): array
    {
        $map = [];
        foreach ($this->loadRows($supplierCode) as $row) {
            $map[mb_strtoupper($row->type_raw)] = true;
        }

        return $map;
    }

    /**
     * Подмешать в списки для LiewoodRetailTransformer.
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>}
     */
    public function extraListsForLiewood(string $supplierCode): array
    {
        $footwear = [];
        $hat = [];
        $socks = [];

        foreach ($this->loadRows($supplierCode) as $row) {
            $t = mb_strtoupper(trim($row->type_raw));
            if ($t === '') {
                continue;
            }
            match ($row->route_bucket) {
                'footwear' => $footwear[] = $t,
                'hat' => $hat[] = $t,
                'socks' => $socks[] = $t,
                default => null,
            };
        }

        return [$footwear, $hat, $socks];
    }

    /**
     * @param  array<string, string>  $typeResolution  raw type -> bucket
     */
    public function saveResolutions(string $supplierCode, array $typeResolution): void
    {
        foreach ($typeResolution as $rawType => $bucket) {
            $raw = trim((string) $rawType);
            $bucket = trim((string) $bucket);
            if ($raw === '' || $bucket === '') {
                continue;
            }
            if (! in_array($bucket, ['footwear', 'hat', 'socks', 'generic'], true)) {
                continue;
            }
            if ($bucket === 'generic') {
                SupplierTypeClassification::updateOrCreate(
                    [
                        'supplier_code' => $supplierCode,
                        'type_raw' => mb_strtoupper($raw),
                    ],
                    ['route_bucket' => 'generic']
                );

                continue;
            }

            SupplierTypeClassification::updateOrCreate(
                [
                    'supplier_code' => $supplierCode,
                    'type_raw' => mb_strtoupper($raw),
                ],
                ['route_bucket' => $bucket]
            );
        }
    }

    /**
     * @return array<int, SupplierTypeClassification>
     */
    public function listForSupplier(string $supplierCode): array
    {
        return SupplierTypeClassification::query()
            ->where('supplier_code', $supplierCode)
            ->orderBy('type_raw')
            ->get()
            ->all();
    }

    /**
     * @return array<int, SupplierTypeClassification>
     */
    private function loadRows(string $supplierCode): array
    {
        return SupplierTypeClassification::query()
            ->where('supplier_code', $supplierCode)
            ->get()
            ->all();
    }
}
