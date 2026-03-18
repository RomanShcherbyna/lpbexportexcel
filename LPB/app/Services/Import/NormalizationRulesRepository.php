<?php

namespace App\Services\Import;

final class NormalizationRulesRepository
{
    /**
     * Storage format (per supplier):
     * {
     *   "Gender": {
     *     "girl": "Girl",
     *     "girls": "Girl"
     *   },
     *   "Season": { ... }
     * }
     *
     * @return array<string,array<string,string>> keyed by target column => (normalized_source => canonical)
     */
    public function loadForSupplier(string $supplierCode): array
    {
        $dir = (string) config('product_import.normalization_rules_dir', storage_path('app/normalization_rules'));
        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $supplierCode . '.json';
        if (!file_exists($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $target => $map) {
            $target = trim((string)$target);
            if ($target === '' || !is_array($map)) {
                continue;
            }

            $colMap = [];
            foreach ($map as $src => $canon) {
                $src = $this->normKey((string)$src);
                $canon = trim((string)$canon);
                if ($src === '' || $canon === '') {
                    continue;
                }
                $colMap[$src] = $canon;
            }

            if ($colMap !== []) {
                $out[$target] = $colMap;
            }
        }

        return $out;
    }

    /**
     * @param array<string,array<string,string>> $rulesByTarget
     */
    public function saveForSupplier(string $supplierCode, array $rulesByTarget): void
    {
        $dir = (string) config('product_import.normalization_rules_dir', storage_path('app/normalization_rules'));
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Normalize keys
        $normalized = [];
        foreach ($rulesByTarget as $target => $map) {
            $target = trim((string)$target);
            if ($target === '' || !is_array($map)) {
                continue;
            }
            $colMap = [];
            foreach ($map as $src => $canon) {
                $src = $this->normKey((string)$src);
                $canon = trim((string)$canon);
                if ($src === '' || $canon === '') {
                    continue;
                }
                $colMap[$src] = $canon;
            }
            if ($colMap !== []) {
                $normalized[$target] = $colMap;
            }
        }

        $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $supplierCode . '.json';
        file_put_contents($path, json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function normKey(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return mb_strtolower($s);
    }
}

