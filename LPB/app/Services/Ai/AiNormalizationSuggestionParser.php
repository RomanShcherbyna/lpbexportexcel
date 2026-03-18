<?php

namespace App\Services\Ai;

use App\DataTransferObjects\NormalizationRuleSuggestionData;

final class AiNormalizationSuggestionParser
{
    /**
     * @param array<int,string> $allowedTargetColumns
     * @return array{ok:bool,rules:array<int,NormalizationRuleSuggestionData>,error:?string}
     */
    public function parse(string $rawContent, array $allowedTargetColumns): array
    {
        $rawContent = trim($rawContent);
        if ($rawContent === '') {
            return ['ok' => false, 'rules' => [], 'error' => 'Empty AI response.'];
        }

        $decoded = json_decode($rawContent, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'rules' => [], 'error' => 'AI response is not valid JSON.'];
        }

        $items = $decoded['rules'] ?? null;
        if (!is_array($items)) {
            return ['ok' => false, 'rules' => [], 'error' => 'AI JSON missing "rules" array.'];
        }

        $allowedSet = array_fill_keys($allowedTargetColumns, true);
        $out = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $target = trim((string)($item['target_column'] ?? ''));
            if ($target === '' || !isset($allowedSet[$target])) {
                // forbidden column
                continue;
            }

            $canonical = trim((string)($item['canonical_value'] ?? ''));
            if ($canonical === '') {
                continue;
            }

            $sourceValues = $item['source_values'] ?? null;
            if (!is_array($sourceValues) || count($sourceValues) === 0) {
                continue;
            }

            $src = [];
            foreach ($sourceValues as $v) {
                $v = trim((string)$v);
                if ($v === '') {
                    continue;
                }
                $src[] = $v;
            }
            $src = array_values(array_unique($src));
            if (count($src) === 0) {
                continue;
            }

            // Reject overly broad rules
            if (count($src) > 20) {
                continue;
            }

            $conf = $item['confidence'] ?? null;
            $confidence = null;
            if (is_numeric($conf)) {
                $confidence = (float)$conf;
                if ($confidence < 0) $confidence = 0.0;
                if ($confidence > 1) $confidence = 1.0;
            }

            $reason = trim((string)($item['reason'] ?? ''));
            if ($reason === '') {
                $reason = 'No reason provided.';
            }

            $out[] = new NormalizationRuleSuggestionData(
                targetColumn: $target,
                sourceValues: $src,
                canonicalValue: $canonical,
                confidence: $confidence,
                reason: $reason,
            );
        }

        return ['ok' => true, 'rules' => $out, 'error' => null];
    }
}

