<?php

namespace App\Services\Ai;

use App\DataTransferObjects\MappingSuggestionData;

final class AiMappingSuggestionParser
{
    /**
     * @param string $rawContent
     * @param array<int,string> $templateColumns
     * @return array{
     *   ok:bool,
     *   suggestions:array<int,MappingSuggestionData>,
     *   error:?string
     * }
     */
    public function parse(string $rawContent, array $templateColumns): array
    {
        $rawContent = trim($rawContent);
        if ($rawContent === '') {
            return ['ok' => false, 'suggestions' => [], 'error' => 'Empty AI response.'];
        }

        $decoded = json_decode($rawContent, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'suggestions' => [], 'error' => 'AI response is not valid JSON.'];
        }

        $items = $decoded['suggestions'] ?? null;
        if (!is_array($items)) {
            return ['ok' => false, 'suggestions' => [], 'error' => 'AI JSON missing "suggestions" array.'];
        }

        $templateSet = array_fill_keys($templateColumns, true);
        $out = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $src = trim((string)($item['source_column'] ?? ''));
            if ($src === '') {
                continue;
            }

            $target = $item['target_column'] ?? null;
            $target = is_string($target) ? trim($target) : null;
            if ($target === '') {
                $target = null;
            }

            if ($target !== null && !isset($templateSet[$target])) {
                // Discard unsafe suggestion
                $target = null;
            }

            $conf = $item['confidence'] ?? null;
            $confidence = null;
            if (is_numeric($conf)) {
                $confidence = (float)$conf;
                if ($confidence < 0 || $confidence > 1) {
                    $confidence = null;
                }
            }

            $reason = trim((string)($item['reason'] ?? ''));
            if ($reason === '') {
                $reason = 'No reason provided.';
            }

            $out[] = new MappingSuggestionData(
                sourceColumn: $src,
                targetColumn: $target,
                confidence: $confidence,
                reason: $reason,
            );
        }

        return ['ok' => true, 'suggestions' => $out, 'error' => null];
    }
}

