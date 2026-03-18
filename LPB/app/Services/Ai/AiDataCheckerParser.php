<?php

namespace App\Services\Ai;

use App\DataTransferObjects\AiWarningData;

final class AiDataCheckerParser
{
    /**
     * @return array{ok:bool,warnings:array<int,AiWarningData>,error:?string}
     */
    public function parse(string $rawContent): array
    {
        $rawContent = trim($rawContent);
        if ($rawContent === '') {
            return ['ok' => false, 'warnings' => [], 'error' => 'Empty AI response.'];
        }

        $decoded = json_decode($rawContent, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'warnings' => [], 'error' => 'AI response is not valid JSON.'];
        }

        $items = $decoded['warnings'] ?? null;
        if (!is_array($items)) {
            return ['ok' => false, 'warnings' => [], 'error' => 'AI JSON missing "warnings" array.'];
        }

        $allowedSeverity = ['critical' => true, 'warning' => true, 'info' => true];
        $out = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = trim((string)($item['type'] ?? ''));
            $severity = trim((string)($item['severity'] ?? ''));
            $message = trim((string)($item['message'] ?? ''));
            $reason = trim((string)($item['reason'] ?? ''));

            if ($type === '' || $message === '' || $reason === '') {
                continue;
            }

            if (!isset($allowedSeverity[$severity])) {
                $severity = 'warning';
            }

            $conf = $item['confidence'] ?? null;
            $confidence = null;
            if (is_numeric($conf)) {
                $confidence = (float)$conf;
                if ($confidence < 0) {
                    $confidence = 0.0;
                } elseif ($confidence > 1) {
                    $confidence = 1.0;
                }
            }

            $affected = $item['affected_rows'] ?? [];
            $affectedRows = [];
            if (is_array($affected)) {
                foreach ($affected as $r) {
                    if (is_int($r)) {
                        $affectedRows[] = $r;
                    } elseif (is_string($r) && ctype_digit($r)) {
                        $affectedRows[] = (int)$r;
                    }
                }
            }
            $affectedRows = array_values(array_unique(array_filter($affectedRows, fn ($n) => $n > 0)));
            sort($affectedRows);

            $out[] = new AiWarningData(
                type: $type,
                severity: $severity,
                confidence: $confidence,
                message: $message,
                reason: $reason,
                affectedRows: $affectedRows
            );
        }

        return ['ok' => true, 'warnings' => $out, 'error' => null];
    }
}

