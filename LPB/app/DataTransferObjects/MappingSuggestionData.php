<?php

namespace App\DataTransferObjects;

final class MappingSuggestionData
{
    public function __construct(
        public readonly string $sourceColumn,
        public readonly ?string $targetColumn,
        public readonly ?float $confidence,
        public readonly string $reason,
    ) {
    }

    /**
     * @return array{source_column:string,target_column:?string,confidence:?float,reason:string}
     */
    public function toArray(): array
    {
        return [
            'source_column' => $this->sourceColumn,
            'target_column' => $this->targetColumn,
            'confidence' => $this->confidence,
            'reason' => $this->reason,
        ];
    }
}

