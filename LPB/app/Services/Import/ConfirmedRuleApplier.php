<?php

namespace App\Services\Import;

final class ConfirmedRuleApplier
{
    /**
     * Apply confirmed normalization rules to mapped template rows.
     *
     * @param array<int,array<string,string>> $rows
     * @param array<string,array<string,string>> $rulesByTargetColumn target => (normalized_source => canonical)
     * @return array{rows:array<int,array<string,string>>,changes:array<int,array{row_number:int,column:string,from:string,to:string}>}
     */
    public function apply(array $rows, array $rulesByTargetColumn): array
    {
        $changes = [];

        $lookup = [];
        foreach ($rulesByTargetColumn as $col => $map) {
            $col = trim((string)$col);
            if ($col === '' || !is_array($map)) {
                continue;
            }
            foreach ($map as $src => $canon) {
                $srcKey = $this->normKey((string)$src);
                $canon = trim((string)$canon);
                if ($srcKey === '' || $canon === '') continue;
                $lookup[$col][$srcKey] = $canon;
            }
        }

        if ($lookup === []) {
            return ['rows' => $rows, 'changes' => []];
        }

        foreach ($rows as $i => $row) {
            foreach ($lookup as $col => $map) {
                if (!array_key_exists($col, $row)) {
                    continue;
                }
                $current = (string)$row[$col];
                $key = $this->normKey($current);
                if ($key === '') {
                    continue;
                }
                if (!isset($map[$key])) {
                    continue;
                }
                $new = (string)$map[$key];
                if ($new === '' || $new === $current) {
                    continue;
                }

                $rows[$i][$col] = $new;
                $changes[] = [
                    'row_number' => $i + 2,
                    'column' => $col,
                    'from' => $current,
                    'to' => $new,
                ];
            }
        }

        return ['rows' => $rows, 'changes' => $changes];
    }

    private function normKey(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = mb_strtolower($s);
        return $s;
    }
}

