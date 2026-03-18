<?php

namespace App\Services\Export;

final class CsvExporter
{
    /**
     * @param array<int,string> $templateColumns
     * @param array<int,array<string,string>> $rows
     */
    public function exportToPath(array $templateColumns, array $rows, string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException("Cannot write CSV: {$path}");
        }

        // UTF-8 with BOM can help Excel; requirement says UTF-8, not explicit BOM.
        // Keep BOM off for strict UTF-8; easy to add later if needed.

        $delimiter = ';';

        fputcsv($fh, $templateColumns, $delimiter);

        foreach ($rows as $row) {
            $line = [];
            foreach ($templateColumns as $col) {
                $line[] = (string)($row[$col] ?? '');
            }
            fputcsv($fh, $line, $delimiter);
        }

        fclose($fh);
    }
}

