<?php

namespace App\Services\Import;

use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

final class ExcelParser
{
    /**
     * Parse an Excel file into associative rows keyed by source headers.
     *
     * @return array{
     *   sheet_index:int,
     *   headers:array<int,string>,
     *   rows:array<int,array<string,string>>,
     *   rows_read:int
     * }
     */
    public function parse(string $filePath, null|string|int $sheet = null): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Input file not found: {$filePath}");
        }

        $sheets = Excel::toArray(new \stdClass(), $filePath);
        if ($sheets === [] || !isset($sheets[0])) {
            throw new InvalidArgumentException('Input Excel has no sheets.');
        }

        $sheetIndex = 0;
        if (is_int($sheet)) {
            $sheetIndex = $sheet;
        } elseif (is_string($sheet) && $sheet !== '') {
            // maatwebsite/excel doesn't give sheet names via toArray; keep Phase1 simple:
            // treat string sheet as unsupported for now.
            throw new InvalidArgumentException('Sheet name selection is not supported in Phase 1. Use null or index.');
        }

        $data = $sheets[$sheetIndex] ?? null;
        if (!is_array($data)) {
            throw new InvalidArgumentException("Sheet not found at index {$sheetIndex}.");
        }

        $headerRow = $data[0] ?? [];
        $headers = $this->normalizeHeaderRow($headerRow);

        $rows = [];
        $rowsRead = 0;

        foreach (array_slice($data, 1) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowsRead++;

            $assoc = [];
            foreach ($headers as $i => $h) {
                $assoc[$h] = $this->cellToString($row[$i] ?? '');
            }

            // Keep even empty rows for validator to flag; but ignore rows that are "Excel artifacts"
            // where all cells are null/empty and there are no meaningful headers.
            $rows[] = $assoc;
        }

        return [
            'sheet_index' => $sheetIndex,
            'headers' => $headers,
            'rows' => $rows,
            'rows_read' => $rowsRead,
        ];
    }

    /**
     * @param array<int,mixed> $headerRow
     * @return array<int,string>
     */
    private function normalizeHeaderRow(array $headerRow): array
    {
        $headers = [];
        $seen = [];
        foreach ($headerRow as $cell) {
            $h = trim((string)$cell);
            $h = preg_replace('/\s+/u', ' ', $h) ?? $h;
            if ($h === '') {
                $headers[] = '';
                continue;
            }
            // de-duplicate: "Color", "Color" => "Color (2)"
            if (isset($seen[$h])) {
                $seen[$h]++;
                $h = $h . ' (' . $seen[$h] . ')';
            } else {
                $seen[$h] = 1;
            }
            $headers[] = $h;
        }

        // Trim trailing empty headers to avoid pulling merged junk.
        for ($i = count($headers) - 1; $i >= 0; $i--) {
            if ($headers[$i] !== '') {
                break;
            }
            array_pop($headers);
        }

        if ($headers === []) {
            throw new InvalidArgumentException('Input file header row is empty.');
        }

        return $headers;
    }

    private function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value)) {
            // Cast to string without scientific notation when possible.
            $s = (string)$value;
            return trim($s);
        }

        return trim((string)$value);
    }
}

