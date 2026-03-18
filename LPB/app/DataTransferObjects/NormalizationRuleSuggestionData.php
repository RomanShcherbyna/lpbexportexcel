<?php

namespace App\DataTransferObjects;

final class NormalizationRuleSuggestionData
{
    /**
     * @param array<int,string> $sourceValues
     */
    public function __construct(
        public readonly string $targetColumn,
        public readonly array $sourceValues,
        public readonly string $canonicalValue,
        public readonly ?float $confidence,
        public readonly string $reason,
    ) {
    }

    /**
     * @return array{target_column:string,source_values:array<int,string>,canonical_value:string,confidence:?float,reason:string}
     */
    public function toArray(): array
    {
        return [
            'target_column' => $this->targetColumn,
            'source_values' => $this->sourceValues,
            'canonical_value' => $this->canonicalValue,
            'confidence' => $this->confidence,
            'reason' => $this->reason,
        ];
    }
}

