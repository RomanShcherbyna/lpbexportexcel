<?php

namespace App\Services\Export;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

final class TemplateLoader
{
    /**
     * @return array<int, string> Template column headers in strict order.
     */
    public function loadTemplateColumns(string $templatePath): array
    {
        // Preferred source: explicit template columns from config.
        $configured = config('product_import.template_columns');
        if (is_array($configured) && $configured !== []) {
            return array_values($configured);
        }

        // Fallback: legacy Excel-based template (kept for backwards compatibility).
        if (!file_exists($templatePath)) {
            throw new InvalidArgumentException("Template not found: {$templatePath}");
        }

        // Cache by path + mtime to avoid re-reading on every request.
        $key = 'product_import.template_columns.' . md5($templatePath . '|' . filemtime($templatePath));

        return Cache::rememberForever($key, function () use ($templatePath) {
            $sheets = Excel::toArray(new \stdClass(), $templatePath);
            $first = $sheets[0] ?? [];
            $headerRow = $first[0] ?? [];

            $cols = [];
            foreach ($headerRow as $cell) {
                $h = trim((string)$cell);
                if ($h === '') {
                    continue;
                }
                $cols[] = $h;
            }

            if ($cols === []) {
                throw new InvalidArgumentException('Template header row is empty.');
            }

            return array_values($cols);
        });
    }
}

