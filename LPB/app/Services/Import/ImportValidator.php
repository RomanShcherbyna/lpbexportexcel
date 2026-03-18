<?php

namespace App\Services\Import;

final class ImportValidator
{
    /**
     * @param array<int,array<string,string>> $outputRows rows already mapped to template columns
     * @param array{sku:string,name:string,price:string} $requiredColumns template column headers
     * @return array{
     *   valid_rows: array<int,array<string,string>>,
     *   warning_rows: array<int,array<string,string>>,
     *   error_rows: array<int,array<string,string>>,
     *   errors: array<int,array{row_number:int,sku:string,message:string}>,
     *   warnings: array<int,array{row_number:int,sku:string,message:string}>,
     *   counts: array{
     *     duplicate_sku_count:int,
     *     missing_sku_count:int,
     *     missing_name_count:int,
     *     missing_price_count:int,
     *     empty_row_count:int
     *   },
     *   summary: array{rows_total:int,rows_valid:int,rows_warning:int,rows_error:int}
     * }
     *
     * Semantics (business rules):
     * - Completely empty rows are treated as errors and are excluded from export.
     * - All other rows are exported even if critical fields are missing.
     * - Missing identifier (Sku + Supplier Product ID), name or price, and duplicate SKU
     *   are reported as warnings and surfaced in the final report, but never block export.
     */
    public function validate(array $outputRows, array $requiredColumns): array
    {
        $errors = [];
        $warnings = [];

        $validRows = [];
        $warningRows = [];
        $errorRows = [];

        $seenSku = [];
        $duplicateSkuCount = 0;
        $missingSkuCount = 0;
        $missingNameCount = 0;
        $missingPriceCount = 0;
        $emptyRowCount = 0;

        $altIdCol = 'Supplier Product ID';

        foreach ($outputRows as $idx => $row) {
            $rowNumber = $idx + 2; // +2 because header is row 1 in Excel
            $skuCol = $requiredColumns['sku'] ?? 'Sku';
            $nameCol = $requiredColumns['name'] ?? 'Product name (EN)';
            $priceCol = $requiredColumns['price'] ?? 'Wholesale Price';

            $sku = trim((string)($row[$skuCol] ?? ''));
            $altId = trim((string)($row[$altIdCol] ?? ''));
            $name = trim((string)($row[$nameCol] ?? ''));
            $price = trim((string)($row[$priceCol] ?? ''));

            $isCompletelyEmpty = true;
            foreach ($row as $v) {
                if (trim((string)$v) !== '') {
                    $isCompletelyEmpty = false;
                    break;
                }
            }

            if ($isCompletelyEmpty) {
                $emptyRowCount++;
                $errors[] = [
                    'row_number' => $rowNumber,
                    'sku' => $sku,
                    'message' => 'Completely empty row.',
                ];
                $errorRows[] = $row;

                // Completely empty rows are not exported at all.
                continue;
            }

            $rowHasWarning = false;

            // Identifier: either Sku or Supplier Product ID can act as ID.
            $hasIdentifier = ($sku !== '' || $altId !== '');
            if (!$hasIdentifier) {
                $missingSkuCount++;
                $rowHasWarning = true;
                $warnings[] = [
                    'row_number' => $rowNumber,
                    'sku' => $sku,
                    'message' => "Missing identifier: both {$skuCol} and {$altIdCol} are empty.",
                ];
            }

            if ($name === '') {
                $missingNameCount++;
                $rowHasWarning = true;
                $warnings[] = [
                    'row_number' => $rowNumber,
                    'sku' => $sku,
                    'message' => "Missing name: {$nameCol} is empty.",
                ];
            }

            if ($price === '') {
                $missingPriceCount++;
                $rowHasWarning = true;
                $warnings[] = [
                    'row_number' => $rowNumber,
                    'sku' => $sku,
                    'message' => "Missing price: {$priceCol} is empty.",
                ];
            }

            if ($sku !== '') {
                if (isset($seenSku[$sku])) {
                    $duplicateSkuCount++;
                    $rowHasWarning = true;
                    $warnings[] = [
                        'row_number' => $rowNumber,
                        'sku' => $sku,
                        'message' => "Duplicate SKU in file: {$sku}.",
                    ];
                } else {
                    $seenSku[$sku] = true;
                }
            }

            // Future extension point for additional warnings.
            if ($this->rowHasWarningsForFuture($row)) {
                $rowHasWarning = true;
                $warnings[] = [
                    'row_number' => $rowNumber,
                    'sku' => $sku,
                    'message' => 'Row has additional warnings.',
                ];
            }

            if ($rowHasWarning) {
                $warningRows[] = $row;
            } else {
                $validRows[] = $row;
            }
        }

        return [
            'valid_rows' => $validRows,
            'warning_rows' => $warningRows,
            'error_rows' => $errorRows,
            'errors' => $errors,
            'warnings' => $warnings,
            'counts' => [
                'duplicate_sku_count' => $duplicateSkuCount,
                'missing_sku_count' => $missingSkuCount,
                'missing_name_count' => $missingNameCount,
                'missing_price_count' => $missingPriceCount,
                'empty_row_count' => $emptyRowCount,
            ],
            'summary' => [
                'rows_total' => count($outputRows),
                'rows_valid' => count($validRows),
                'rows_warning' => count($warningRows),
                'rows_error' => count($errorRows),
            ],
        ];
    }

    /**
     * Keep for future extension without changing pipeline shape.
     * Strict Phase 1 returns false.
     */
    private function rowHasWarningsForFuture(array $row): bool
    {
        return false;
    }
}

