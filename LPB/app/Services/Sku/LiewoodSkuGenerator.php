<?php

namespace App\Services\Sku;

final class LiewoodSkuGenerator
{
    /**
     * @param  array<string,string>  $row Template-mapped output row
     * @param  array<int,string>|null  $sizeColumnPriorityOverride  Порядок колонок размера для токена SKU (иначе дефолтный список)
     * @return array<string,string> Updated row (Sku filled only if empty)
     */
    public function fillSkuIfMissing(array $row, string $skuColumn = 'Sku', ?array $sizeColumnPriorityOverride = null): array
    {
        $existing = trim((string)($row[$skuColumn] ?? ''));
        if ($existing !== '') {
            return $row;
        }

        // Liewood SKU format (as in "final - Hoja 1.csv"):
        // LI-{2 chars from product first word}-{2 chars from first word of color description}-{size token}
        $productName = $this->pickFirstNonEmpty($row, [
            'Product name (EN)',
            'Nazwa produktu (EN)',
        ]);

        $colorDescription = $this->pickFirstNonEmpty($row, [
            'Color describtion',
            'Color Description EN',
            'Color Code',
        ]);

        $colorNameForToken = $this->chooseColorValueForToken($colorDescription);

        $productToken = $this->tokenFromFirstWord($productName);
        $colorToken = $this->tokenFromFirstWord($colorNameForToken);

        // Size: first non-empty among size-related columns.
        // Priority matches your sample: sandals (age size), caps (KG size), swimwear (KG size ranges), one-size, etc.
        $sizeCols = $sizeColumnPriorityOverride ?? [
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
        $sizeRaw = $this->pickFirstNonEmpty($row, $sizeCols);
        $sizeToken = $this->normalizeSizeTokenForSku($sizeRaw);

        if ($productToken !== '' && $colorToken !== '' && $sizeToken !== '') {
            $row[$skuColumn] = "LI-{$productToken}-{$colorToken}-{$sizeToken}";
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

    private function chooseColorValueForToken(string $value): string
    {
        $v = trim($value);
        if ($v === '') return '';

        // If the value is purely numeric (a "Color Code"), it can't form a proper token.
        // In that case we will return empty and SKU generation will stay empty (or be fixed via mapping).
        if (preg_match('/^[0-9]+$/u', $v) === 1) {
            return '';
        }

        return $v;
    }

    private function tokenFromFirstWord(string $value): string
    {
        $v = trim($value);
        if ($v === '') return '';

        $firstWord = preg_split('/\s+/u', $v, -1, PREG_SPLIT_NO_EMPTY)[0] ?? '';
        $clean = preg_replace('/[^A-Za-z0-9]/u', '', $firstWord) ?? '';
        $clean = mb_strtoupper(trim($clean));

        if ($clean === '') return '';
        if (mb_strlen($clean) >= 2) {
            return mb_substr($clean, 0, 2);
        }
        return $clean;
    }

    private function normalizeSizeTokenForSku(string $value): string
    {
        $v = trim($value);
        if ($v === '') return '';

        $v = str_replace(',', '.', $v);
        $v = mb_strtoupper($v);
        $v = str_replace(["\t", "\r", "\n"], ' ', $v);
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

        // Keep characters observed in your sample SKU sizes:
        // digits, letters (ONE SIZE / KG / Y / M), '/', '-', and spaces.
        $v = preg_replace('/[^A-Z0-9\/\-\.\s]+/u', '', $v) ?? $v;
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

        return trim($v);
    }
}

