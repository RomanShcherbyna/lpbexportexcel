<?php

namespace App\Services\Import;

use App\DataTransferObjects\MappingSuggestionData;

final class MappingResolutionService
{
    /**
     * Build final mapping with priority:
     * manual override > saved mapping > AI suggestion.
     *
     * @param array<int,string> $supplierHeaders
     * @param array<string,string> $savedMapping source => template
     * @param array<int,MappingSuggestionData> $aiSuggestions
     * @param array<string,?string> $manualSelection source => template|null
     * @param array<int,string> $templateColumns
     * @return array{
     *   final_mapping: array<string,string>,
     *   rows: array<int,array{
     *     source:string,
     *     saved:?string,
     *     ai:?string,
     *     confidence:?float,
     *     reason:?string,
     *     final:?string
     *   }>
     * }
     */
    public function resolve(
        array $supplierHeaders,
        array $savedMapping,
        array $aiSuggestions,
        array $manualSelection,
        array $templateColumns
    ): array {
        $templateSet = array_fill_keys($templateColumns, true);
        $templateByNormalized = [];
        foreach ($templateColumns as $templateColumn) {
            $norm = $this->normalizeHeaderKey((string)$templateColumn);
            if (!isset($templateByNormalized[$norm])) {
                $templateByNormalized[$norm] = (string)$templateColumn;
            }
        }

        // Index AI suggestions by source column
        $aiBySource = [];
        foreach ($aiSuggestions as $s) {
            $aiBySource[$s->sourceColumn] = $s;
        }

        $rows = [];
        $finalMapping = [];

        foreach ($supplierHeaders as $source) {
            $source = trim((string)$source);
            if ($source === '') {
                continue;
            }

            $saved = $savedMapping[$source] ?? null;
            $saved = is_string($saved)
                ? $this->resolveTemplateTarget($saved, $templateSet, $templateByNormalized)
                : null;

            $ai = $aiBySource[$source] ?? null;
            $aiTarget = $ai?->targetColumn;
            if ($aiTarget !== null) {
                $aiTarget = $this->resolveTemplateTarget($aiTarget, $templateSet, $templateByNormalized);
            }

            $manual = array_key_exists($source, $manualSelection) ? $manualSelection[$source] : null;
            $manual = is_string($manual) ? $manual : $manual;
            if ($manual === '' || $manual === '__unmapped__') {
                $manual = null;
            }
            if ($manual !== null) {
                $manual = $this->resolveTemplateTarget($manual, $templateSet, $templateByNormalized);
            }

            $final = $manual ?? $saved ?? $aiTarget ?? null;

            if ($final !== null) {
                $finalMapping[$source] = $final;
            }

            $rows[] = [
                'source' => $source,
                'saved' => $saved,
                'ai' => $aiTarget,
                'confidence' => $ai?->confidence,
                'reason' => $ai?->reason,
                'final' => $final,
            ];
        }

        return [
            'final_mapping' => $finalMapping,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string,bool> $templateSet
     * @param array<string,string> $templateByNormalized
     */
    private function resolveTemplateTarget(string $candidate, array $templateSet, array $templateByNormalized): ?string
    {
        if (isset($templateSet[$candidate])) {
            return $candidate;
        }

        if (trim($candidate) === '') {
            return null;
        }

        $norm = $this->normalizeHeaderKey($candidate);
        return $templateByNormalized[$norm] ?? null;
    }

    private function normalizeHeaderKey(string $value): string
    {
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower($value);
    }
}

