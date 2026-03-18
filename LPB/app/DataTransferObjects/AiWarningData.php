<?php

namespace App\DataTransferObjects;

final class AiWarningData
{
    /**
     * @param array<int,int> $affectedRows
     */
    public function __construct(
        public readonly string $type,
        public readonly string $severity, // critical|warning|info
        public readonly ?float $confidence,
        public readonly string $message,
        public readonly string $reason,
        public readonly array $affectedRows,
    ) {
    }

    /**
     * @return array{type:string,severity:string,confidence:?float,message:string,reason:string,affected_rows:array<int,int>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'confidence' => $this->confidence,
            'message' => $this->message,
            'reason' => $this->reason,
            'affected_rows' => $this->affectedRows,
        ];
    }
}

