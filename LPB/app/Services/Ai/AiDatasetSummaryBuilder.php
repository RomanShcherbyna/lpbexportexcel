<?php

namespace App\Services\Ai;

final class AiDatasetSummaryBuilder
{
    /**
     * Build compact payload for AI data checker.
     *
     * @param array<int,string> $templateColumns
     * @param array<string,string> $confirmedMapping source => template
     * @param array<int,array<string,string>> $mappedRows full mapped rows (template headers as keys)
     * @param array{
     *   summary:array{rows_total:int,rows_valid:int,rows_warning:int,rows_error:int},
     *   counts?:array<string,int>,
     *   errors:array<int,array{row_number:int,sku:string,message:string}>,
     *   warnings:array<int,array{row_number:int,sku:string,message:string}>
     * } $deterministic
     * @return array<string,mixed>
     */
    public function build(
        array $templateColumns,
        array $confirmedMapping,
        array $mappedRows,
        array $deterministic
    ): array {
        $rowCount = count($mappedRows);

        $samplesByCol = $this->buildColumnValueSamples($mappedRows, [
            'Product name (EN)',
            'Category',
            'Sub-category',
            'Wholesale Price',
            'Recommended Retail Price',
            'Sku',
            'EAN / Barcode',
            'Age Size',
            'Qty',
            'Gender',
        ], 3);

        $sampleRows = $this->pickRepresentativeRows($mappedRows, 8);
        $problemRows = $this->pickHeuristicProblemRows($mappedRows, 8);

        $datasetSummary = [
            'row_count' => $rowCount,
            'rows_valid' => (int)($deterministic['summary']['rows_valid'] ?? 0),
            'rows_error' => (int)($deterministic['summary']['rows_error'] ?? 0),
            'rows_warning' => (int)($deterministic['summary']['rows_warning'] ?? 0),
            'duplicate_sku_count' => (int)($deterministic['counts']['duplicate_sku_count'] ?? 0),
            'missing_price_count' => (int)($deterministic['counts']['missing_price_count'] ?? 0),
            'missing_name_count' => (int)($deterministic['counts']['missing_name_count'] ?? 0),
            'missing_sku_count' => (int)($deterministic['counts']['missing_sku_count'] ?? 0),
            'empty_row_count' => (int)($deterministic['counts']['empty_row_count'] ?? 0),
        ];

        return [
            'template_columns' => array_values($templateColumns),
            'confirmed_mapping' => $confirmedMapping,
            'dataset_summary' => $datasetSummary,
            'column_value_samples' => $samplesByCol,
            'sample_rows' => $sampleRows,
            'heuristic_problem_rows' => $problemRows,
            'deterministic_errors_sample' => array_slice((array)($deterministic['errors'] ?? []), 0, 12),
        ];
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @param array<int,string> $columns
     * @return array<string,array<int,string>>
     */
    private function buildColumnValueSamples(array $rows, array $columns, int $limitPerCol): array
    {
        $out = [];
        foreach ($columns as $col) {
            $set = [];
            foreach ($rows as $row) {
                if (!array_key_exists($col, $row)) {
                    continue;
                }
                $v = trim((string)$row[$col]);
                if ($v === '') {
                    continue;
                }
                $set[$v] = true;
                if (count($set) >= $limitPerCol) {
                    break;
                }
            }
            if ($set !== []) {
                $out[$col] = array_slice(array_keys($set), 0, $limitPerCol);
            }
        }
        return $out;
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array<int,array<string,string>>
     */
    private function pickRepresentativeRows(array $rows, int $limit): array
    {
        if ($rows === []) {
            return [];
        }
        $out = [];
        $out[] = $rows[0];
        if (count($rows) > 1) {
            $out[] = $rows[(int)floor(count($rows) / 2)];
        }
        if (count($rows) > 2) {
            $out[] = $rows[count($rows) - 1];
        }

        // Fill with early rows
        foreach ($rows as $r) {
            $out[] = $r;
            if (count($out) >= $limit) {
                break;
            }
        }

        // De-dup by serialized row snapshot
        $dedup = [];
        $final = [];
        foreach ($out as $r) {
            $k = md5(json_encode($r));
            if (isset($dedup[$k])) {
                continue;
            }
            $dedup[$k] = true;
            $final[] = $r;
            if (count($final) >= $limit) {
                break;
            }
        }

        return $final;
    }

    /**
     * Heuristic suspects to help AI (does not change data, does not block export).
     *
     * @param array<int,array<string,string>> $rows
     * @return array<int,array{row_number:int,row:array<string,string>,hint:string}>
     */
    private function pickHeuristicProblemRows(array $rows, int $limit): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2;

            $name = trim((string)($row['Product name (EN)'] ?? ''));
            $sku = trim((string)($row['Sku'] ?? ''));
            $wh = trim((string)($row['Wholesale Price'] ?? ''));
            $rrp = trim((string)($row['Recommended Retail Price'] ?? ''));

            $lname = mb_strtolower($name);
            if ($name !== '' && (str_contains($lname, 'freight') || str_contains($lname, 'shipping') || str_contains($lname, 'delivery'))) {
                $out[] = ['row_number' => $rowNumber, 'row' => $row, 'hint' => 'name_contains_freight_shipping'];
            }

            if ($name !== '' && mb_strlen($name) > 140) {
                $out[] = ['row_number' => $rowNumber, 'row' => $row, 'hint' => 'name_too_long_paragraph_like'];
            }

            if ($sku !== '' && !preg_match('/^[A-Za-z0-9\-_]+$/', $sku)) {
                $out[] = ['row_number' => $rowNumber, 'row' => $row, 'hint' => 'sku_has_unusual_characters'];
            }

            $whN = $this->toNumberOrNull($wh);
            $rrpN = $this->toNumberOrNull($rrp);
            if ($whN !== null && $rrpN !== null && $whN > $rrpN) {
                $out[] = ['row_number' => $rowNumber, 'row' => $row, 'hint' => 'wholesale_greater_than_retail'];
            }

            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private function toNumberOrNull(string $s): ?float
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        // Remove common currency fragments and spaces, keep digits/./,
        $t = preg_replace('/[^\d\.,\-]/', '', $s) ?? $s;
        $t = str_replace(',', '.', $t);
        if (!is_numeric($t)) {
            return null;
        }
        return (float)$t;
    }
}

