<?php

namespace App\Services\Import;

final class RowNormalizer
{
    /**
     * @param array<string,string> $row
     * @return array<string,string>
     */
    public function normalizeRow(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $key = $this->normalizeString($k);
            $val = $this->normalizeCellValue($v);
            $out[$key] = $val;
        }
        return $out;
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array<int,array<string,string>>
     */
    public function normalizeRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->normalizeRow($row);
        }
        return $out;
    }

    private function normalizeString(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return $s;
    }

    private function normalizeCellValue(mixed $v): string
    {
        if ($v === null) {
            return '';
        }

        $s = is_string($v) ? $v : (string)$v;
        $s = trim($s);

        // Convert none-like placeholders to empty.
        $lower = mb_strtolower($s);
        if (in_array($lower, ['nan', 'none', 'null', 'n/a', 'na', '-', '—'], true)) {
            return '';
        }

        // Normalize line breaks (keep content, remove extra spacing).
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;

        return $s;
    }
}

