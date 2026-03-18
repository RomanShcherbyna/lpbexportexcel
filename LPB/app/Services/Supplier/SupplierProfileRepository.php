<?php

namespace App\Services\Supplier;

use App\DataTransferObjects\SupplierProfileData;

final class SupplierProfileRepository
{
    private function profilesDir(): string
    {
        $base = config('product_import.supplier_profiles_dir', storage_path('app/supplier_profiles'));
        return rtrim((string)$base, DIRECTORY_SEPARATOR);
    }

    public function load(string $supplierCode): SupplierProfileData
    {
        $path = $this->profilesDir() . DIRECTORY_SEPARATOR . $supplierCode . '.json';
        if (!file_exists($path)) {
            return $this->emptyProfile($supplierCode);
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return $this->emptyProfile($supplierCode);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->emptyProfile($supplierCode);
        }

        $version = (int)($decoded['version'] ?? 1);
        $name = trim((string)($decoded['name'] ?? $supplierCode));

        $mapping = [];
        foreach ((array)($decoded['mapping'] ?? []) as $src => $dst) {
            $src = trim((string)$src);
            $dst = trim((string)$dst);
            if ($src === '' || $dst === '') {
                continue;
            }
            $mapping[$src] = $dst;
        }

        $normRules = [];
        foreach ((array)($decoded['normalization_rules'] ?? []) as $col => $map) {
            $col = trim((string)$col);
            if ($col === '' || !is_array($map)) {
                continue;
            }
            $colMap = [];
            foreach ($map as $src => $canon) {
                $src = $this->normKey((string)$src);
                $canon = trim((string)$canon);
                if ($src === '' || $canon === '') {
                    continue;
                }
                $colMap[$src] = $canon;
            }
            if ($colMap !== []) {
                $normRules[$col] = $colMap;
            }
        }

        $rowFilters = [];
        foreach ((array)($decoded['row_filters'] ?? []) as $f) {
            $f = trim((string)$f);
            if ($f !== '') {
                $rowFilters[] = $f;
            }
        }

        $defaultSettings = (array)($decoded['default_settings'] ?? []);

        return new SupplierProfileData(
            supplierCode: $supplierCode,
            version: $version,
            name: $name,
            mapping: $mapping,
            normalizationRules: $normRules,
            rowFilters: $rowFilters,
            defaultSettings: $defaultSettings,
        );
    }

    public function save(SupplierProfileData $profile): void
    {
        $dir = $this->profilesDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $data = $profile->toArray();
        if (!isset($data['version']) || (int)$data['version'] < 1) {
            $data['version'] = 1;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $profile->supplierCode . '.json';
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function emptyProfile(string $supplierCode): SupplierProfileData
    {
        return new SupplierProfileData(
            supplierCode: $supplierCode,
            version: 1,
            name: $supplierCode,
            mapping: [],
            normalizationRules: [],
            rowFilters: [],
            defaultSettings: [],
        );
    }

    private function normKey(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return mb_strtolower($s);
    }
}

