<?php

namespace App\Services\Import;

use InvalidArgumentException;

final class RowMapper
{
    /**
     * @param array<int,array<string,string>> $supplierRows normalized associative rows by supplier headers
     * @param array<string,string> $columnMapping supplier source header => template header
     * @param array<int,string> $templateColumns strict ordered list
     * @return array{
     *   output_rows: array<int,array<string,string>>,
     *   mapping_status: array<int,array{source:string,target:string,status:string}>
     * }
     */
    public function map(array $supplierRows, array $columnMapping, array $templateColumns): array
    {
        $templateSet = array_fill_keys($templateColumns, true);
        $sourceKeyMap = $this->buildSourceKeyMap($supplierRows);

        // Validate mapping targets belong to template.
        foreach ($columnMapping as $src => $dst) {
            if (!isset($templateSet[$dst])) {
                // #region agent log
                @file_put_contents(
                    base_path('.cursor/debug-9a7511.log'),
                    json_encode([
                        'sessionId' => '9a7511',
                        'runId' => 'debug-brand-sku',
                        'hypothesisId' => 'H5',
                        'location' => 'LPB/app/Services/Import/RowMapper.php:map',
                        'message' => 'Mapping target missing in template set',
                        'data' => [
                            'source' => $src,
                            'target' => $dst,
                            'target_trimmed' => trim((string)$dst),
                            'template_contains_target' => isset($templateSet[$dst]),
                            'template_contains_target_trimmed' => isset($templateSet[trim((string)$dst)]),
                        ],
                        'timestamp' => (int) (microtime(true) * 1000),
                    ]) . PHP_EOL,
                    FILE_APPEND
                );
                // #endregion
                throw new InvalidArgumentException("Mapping target not in template: '{$dst}' (source '{$src}')");
            }
        }

        $outputRows = [];

        foreach ($supplierRows as $row) {
            $out = [];
            foreach ($templateColumns as $col) {
                $out[$col] = '';
            }

            foreach ($columnMapping as $src => $dst) {
                $resolvedSrc = $this->resolveSourceKey((string)$src, $sourceKeyMap);
                if ($resolvedSrc === null || !array_key_exists($resolvedSrc, $row)) {
                    // Missing source column => keep empty.
                    continue;
                }
                $out[$dst] = $row[$resolvedSrc];
            }

            // Keep strict order when exporting later; associative by template header is fine.
            $outputRows[] = $out;
        }

        $mappingStatus = [];
        foreach ($columnMapping as $src => $dst) {
            $resolvedSrc = $this->resolveSourceKey((string)$src, $sourceKeyMap);
            $mappingStatus[] = [
                'source' => $src,
                'target' => $dst,
                'status' => $resolvedSrc === null
                    ? 'missing_source'
                    : ($resolvedSrc === $src ? 'mapped_exact' : 'mapped_normalized'),
            ];
        }

        return [
            'output_rows' => $outputRows,
            'mapping_status' => $mappingStatus,
        ];
    }

    /**
     * Build map of configured source key => actual row key.
     *
     * @param array<int,array<string,string>> $supplierRows
     * @return array{exact:array<string,string>,normalized:array<string,string>}
     */
    private function buildSourceKeyMap(array $supplierRows): array
    {
        if ($supplierRows === [] || !is_array($supplierRows[0])) {
            return ['exact' => [], 'normalized' => []];
        }

        $actualKeys = array_keys($supplierRows[0]);
        $exact = [];
        $normalizedIndex = [];
        foreach ($actualKeys as $key) {
            $k = (string)$key;
            $exact[$k] = $k;
            $norm = $this->normalizeHeaderKey((string)$key);
            if (!isset($normalizedIndex[$norm])) {
                $normalizedIndex[$norm] = $k;
            }
        }

        return [
            'exact' => $exact,
            'normalized' => $normalizedIndex,
        ];
    }

    /**
     * @param array{exact:array<string,string>,normalized:array<string,string>} $sourceKeyMap
     */
    private function resolveSourceKey(string $configuredSource, array $sourceKeyMap): ?string
    {
        $configuredSource = trim($configuredSource);
        if ($configuredSource === '') {
            return null;
        }

        if (isset($sourceKeyMap['exact'][$configuredSource])) {
            return $sourceKeyMap['exact'][$configuredSource];
        }

        $normalized = $this->normalizeHeaderKey($configuredSource);
        return $sourceKeyMap['normalized'][$normalized] ?? null;
    }

    private function normalizeHeaderKey(string $value): string
    {
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower($value);
    }
}

