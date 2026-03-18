<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class XlsxExporter
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

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header row (row 1)
        foreach ($templateColumns as $i => $col) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $col);
        }

        // Data rows
        $rowIdx = 2;
        foreach ($rows as $row) {
            foreach ($templateColumns as $i => $col) {
                // set as explicit string to preserve leading zeros
                $sheet->setCellValueExplicitByColumnAndRow(
                    $i + 1,
                    $rowIdx,
                    (string)($row[$col] ?? ''),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
            }
            $rowIdx++;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
    }
}

