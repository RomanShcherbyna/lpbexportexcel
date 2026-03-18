<?php

namespace App\Services\Import;

final class AiNormalizationPreparationService
{
    /**
     * Build compact distinct-values summary per relevant target column.
     *
     * @param array<int,array<string,string>> $mappedRows
     * @param array<int,string> $relevantTargetColumns
     * @return array{columns:array<string,array{distinct_values:array<int,array{value:string,count:int}>}>}
     */
    public function buildDistinctValueSummary(array $mappedRows, array $relevantTargetColumns): array
    {
        $out = ['columns' => []];

        foreach ($relevantTargetColumns as $col) {
            $counts = [];
            foreach ($mappedRows as $row) {
                if (!array_key_exists($col, $row)) {
                    continue;
                }
                $v = trim((string)$row[$col]);
                if ($v === '') {
                    continue;
                }
                $key = $this->normKey($v);
                if (!isset($counts[$key])) {
                    $counts[$key] = ['value' => $v, 'count' => 0];
                }
                $counts[$key]['count']++;
            }

            // sort by count desc
            usort($counts, fn ($a, $b) => ($b['count'] <=> $a['count']));

            // clamp to keep payload compact
            $out['columns'][$col] = [
                'distinct_values' => array_slice(array_values($counts), 0, 40),
            ];
        }

        return $out;
    }

    private function normKey(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return mb_strtolower($s);
    }
}

