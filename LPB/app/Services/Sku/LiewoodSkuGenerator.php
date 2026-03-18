<?php

namespace App\Services\Sku;

final class LiewoodSkuGenerator
{
    /**
     * @param array<string,string> $row Template-mapped output row
     * @param string $skuColumn Template SKU column name (usually "Sku")
     * @return array<string,string> Updated row (Sku filled only if empty)
     */
    public function fillSkuIfMissing(array $row, string $skuColumn = 'Sku'): array
    {
        $existing = trim((string)($row[$skuColumn] ?? ''));
        if ($existing !== '') {
            return $row;
        }

        $supplierProductId = $this->normalizeToken($row['Supplier Product ID'] ?? '');
        $colorCode = $this->normalizeToken($row['Color Code'] ?? '');
        $sizeRaw = $this->pickFirstNonEmpty($row, [
            'Общая колонка размеров',
            'Shoe size',
            'Age Size ',
            'Height Size',
            'Hats Size',
            'Socks Size',
            'Size Xs-Xl',
        ]);
        $size = $this->normalizeSize($sizeRaw);

        if ($supplierProductId !== '' && $colorCode !== '' && $size !== '') {
            $row[$skuColumn] = "LW-{$supplierProductId}-{$colorCode}-{$size}";
            return $row;
        }

        // Fallback: if size is missing but EAN exists, include it to reduce collisions.
        $ean = $this->normalizeEan($row['EAN / Barcode'] ?? '');
        if ($supplierProductId !== '' && $colorCode !== '' && $ean !== '') {
            $row[$skuColumn] = "LW-{$supplierProductId}-{$colorCode}-EAN{$ean}";
            return $row;
        }

        return $row;
    }

    /**
     * @param array<string,string> $row
     * @param array<int,string> $columns
     */
    private function pickFirstNonEmpty(array $row, array $columns): string
    {
        foreach ($columns as $col) {
            $v = trim((string)($row[$col] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    private function normalizeToken(string $value): string
    {
        $v = mb_strtoupper(trim($value));
        $v = preg_replace('/\s+/u', '', $v) ?? $v;
        $v = preg_replace('/[^A-Z0-9-]+/u', '', $v) ?? $v;
        $v = preg_replace('/-+/', '-', $v) ?? $v;
        return trim($v, '-');
    }

    private function normalizeEan(string $value): string
    {
        $v = trim($value);
        $v = preg_replace('/[^0-9]+/', '', $v) ?? $v;
        return $v;
    }

    private function normalizeSize(string $value): string
    {
        $v = mb_strtoupper(trim($value));
        $v = str_replace(["\t", "\r", "\n"], ' ', $v);
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
        $v = str_replace([' ', '/', '_'], '-', $v);
        $v = preg_replace('/[^A-Z0-9-]+/u', '', $v) ?? $v;
        $v = preg_replace('/-+/', '-', $v) ?? $v;
        return trim($v, '-');
    }
}

