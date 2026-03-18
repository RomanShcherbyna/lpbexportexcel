<?php

namespace App\Services\Ai;

final class AiWarningFormatter
{
    public function severityClass(string $severity): string
    {
        return match ($severity) {
            'critical' => 'critical',
            'warning' => 'warning',
            default => 'info',
        };
    }
}

