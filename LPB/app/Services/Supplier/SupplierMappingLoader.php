<?php

namespace App\Services\Supplier;

use Illuminate\Support\Arr;
use InvalidArgumentException;

final class SupplierMappingLoader
{
    /**
     * @return array{
     *   supplier_code:string,
     *   supplier_name:string,
     *   sheet:null|string|int,
     *   column_mapping:array<string,string>
     * }
     */
    public function load(string $supplierCode): array
    {
        $path = config_path('suppliers/' . $supplierCode . '.php');
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Unknown supplier: {$supplierCode}");
        }

        $cfg = require $path;
        if (!is_array($cfg)) {
            throw new InvalidArgumentException("Invalid supplier config: {$supplierCode}");
        }

        $mapping = Arr::get($cfg, 'column_mapping', []);
        if (!is_array($mapping)) {
            throw new InvalidArgumentException("Invalid column_mapping for supplier: {$supplierCode}");
        }

        $normalizedMapping = [];
        foreach ($mapping as $src => $dst) {
            $src = trim((string)$src);
            $dst = (string)$dst;
            if ($src === '' || trim($dst) === '') {
                continue;
            }
            $normalizedMapping[$src] = $dst;
        }

        return [
            'supplier_code' => trim((string)Arr::get($cfg, 'supplier_code', $supplierCode)),
            'supplier_name' => trim((string)Arr::get($cfg, 'supplier_name', $supplierCode)),
            'sheet' => Arr::get($cfg, 'sheet', null),
            'column_mapping' => $normalizedMapping,
        ];
    }
}

