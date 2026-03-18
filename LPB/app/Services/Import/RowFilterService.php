<?php

namespace App\Services\Import;

final class RowFilterService
{
    /**
     * @param array<int,array<string,string>> $rows
     * @param array<int,string> $filters
     * @return array{
     *   kept: array<int,array<string,string>>,
     *   excluded: array<int,array<string,string>>
     * }
     */
    public function filter(array $rows, array $filters, string $column = 'Product name (EN)'): array
    {
        $filters = array_values(array_filter(array_map(
            fn ($v) => mb_strtolower(trim((string)$v)),
            $filters
        ), fn ($v) => $v !== ''));

        if ($filters === []) {
            return ['kept' => $rows, 'excluded' => []];
        }

        $kept = [];
        $excluded = [];

        foreach ($rows as $row) {
            $value = mb_strtolower(trim((string)($row[$column] ?? '')));
            $match = false;
            foreach ($filters as $f) {
                if ($f !== '' && str_contains($value, $f)) {
                    $match = true;
                    break;
                }
            }

            if ($match) {
                $excluded[] = $row;
            } else {
                $kept[] = $row;
            }
        }

        return ['kept' => $kept, 'excluded' => $excluded];
    }
}

