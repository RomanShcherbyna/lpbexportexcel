<?php

namespace App\Console\Commands;

use App\Models\SupplierTypeClassification;
use App\Services\Import\SupplierTypeNormalizer;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Читает CSV (колонка Type или item SubCategory), дедуп по канону, пишет в supplier_type_classifications.
 */
final class SeedLiewoodTypeClassificationsFromCsv extends Command
{
    protected $signature = 'lpb:seed-liewood-types
                            {path : Путь к CSV (разделитель ; или ,, колонка Type или item SubCategory)}
                            {--dry-run : Только показать, без записи в БД}';

    protected $description = 'Импорт уникальных Type из CSV в привязки Liewood (колонка Type или item SubCategory; дедуп по канону)';

    public function handle(SupplierTypeNormalizer $normalizer): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $rows = $this->parseDistinctTypes($path, $normalizer);
        if ($rows === []) {
            $this->warn('Не найдено колонок Type / item SubCategory или нет значений.');

            return self::FAILURE;
        }

        $this->info('Уникальных записей после канона: '.count($rows));
        $dry = (bool) $this->option('dry-run');

        foreach ($rows as $typeRaw => $bucket) {
            $line = "{$typeRaw} → {$bucket}";
            if ($dry) {
                $this->line($line);
            } else {
                SupplierTypeClassification::query()->updateOrCreate(
                    [
                        'supplier_code' => 'liewood',
                        'type_raw' => $typeRaw,
                    ],
                    ['route_bucket' => $bucket]
                );
                $this->line($line);
            }
        }

        if (! $dry) {
            $this->info('Готово. Проверьте /settings/brands/liewood');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> type_raw UPPER (короткий представитель канона) => bucket
     */
    private function parseDistinctTypes(string $path, SupplierTypeNormalizer $normalizer): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException("Cannot open {$path}");
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return [];
        }
        rewind($handle);
        if ($bom !== "\xEF\xBB\xBF") {
            // already at start
        } else {
            fread($handle, 3);
        }

        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $headers = array_map(fn ($h) => trim((string) $h, " \t\n\r\0\x0B\""), $headers);

        $colIdx = null;
        foreach ($headers as $i => $h) {
            if ($h === 'Type') {
                $colIdx = $i;
                break;
            }
        }
        if ($colIdx === null) {
            foreach ($headers as $i => $h) {
                if ($h === 'item SubCategory') {
                    $colIdx = $i;
                    break;
                }
            }
        }

        if ($colIdx === null) {
            fclose($handle);

            return [];
        }

        /** @var array<string, array{type_raw: string, bucket: string}> $byCanon */
        $byCanon = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) < $colIdx + 1) {
                continue;
            }
            $val = trim((string) ($row[$colIdx] ?? ''));
            if ($val === '') {
                continue;
            }
            $upper = mb_strtoupper($val);
            $canon = $normalizer->canonicalForRouting($upper);
            $bucket = $this->resolveBucket($upper, $canon, $normalizer);
            if ($canon === '') {
                continue;
            }
            if (! isset($byCanon[$canon])) {
                $byCanon[$canon] = ['type_raw' => $upper, 'bucket' => $bucket];

                continue;
            }
            if (mb_strlen($upper) < mb_strlen($byCanon[$canon]['type_raw'])) {
                $byCanon[$canon]['type_raw'] = $upper;
            }
            // Вёдро от канона/явной карты (один канон — одно ведро).
            $byCanon[$canon]['bucket'] = $this->resolveBucket($byCanon[$canon]['type_raw'], $canon, $normalizer);
        }

        fclose($handle);

        $distinct = [];
        foreach ($byCanon as $canon => $data) {
            $data['bucket'] = $this->resolveBucket($data['type_raw'], $canon, $normalizer);
            $distinct[$data['type_raw']] = $data['bucket'];
        }
        ksort($distinct);

        return $distinct;
    }

    /**
     * Сначала config/liewood_type_default_buckets.php (точное Type и канон), затем эвристики.
     */
    private function resolveBucket(string $typeUpper, string $canon, SupplierTypeNormalizer $normalizer): string
    {
        $map = (array) config('liewood_type_default_buckets.map', []);
        if (isset($map[$typeUpper])) {
            return (string) $map[$typeUpper];
        }
        if ($canon !== '' && isset($map[$canon])) {
            return (string) $map[$canon];
        }

        return $this->classifyBucket($typeUpper, $normalizer);
    }

    private function classifyBucket(string $typeUpper, SupplierTypeNormalizer $normalizer): string
    {
        // Игрушки/игры: не цепляемся к подстроке SHOE в других словах, всегда generic.
        if ($normalizer->isToyOrPlaySemanticType($typeUpper)) {
            return 'generic';
        }

        $footwear = ['SANDALS', 'SLIPPERS', 'BOOTS', 'SHOES', 'FOOTWEAR', 'SNEAKERS', 'SWIM SHOE'];
        $hats = ['HATS/CAP', 'HATS', 'CAP'];

        if (in_array($typeUpper, $footwear, true)) {
            return 'footwear';
        }
        if (in_array($typeUpper, $hats, true)) {
            return 'hat';
        }
        if (preg_match('/\bsocks?\b/i', $typeUpper) === 1) {
            return 'socks';
        }
        if (preg_match('/\b(SHOE|SANDAL|SNEAKER|BOOT|SLIPPER|FOOTWEAR)\b/i', $typeUpper) === 1) {
            return 'footwear';
        }

        return 'generic';
    }
}
