<?php

namespace App\Services\Import;

final class ImportInstructionService
{
    /**
     * @param array<int, string> $templateColumns
     * @param array<int, array{source:string,target:string,status:string}> $mappingStatus
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function buildPassport(
        string $supplierCode,
        string $supplierName,
        array $templateColumns,
        array $mappingStatus,
        array $context = [],
    ): array {
        return [
            'meta' => [
                'supplier_code' => $supplierCode,
                'supplier_name' => $supplierName,
                'generated_at' => gmdate('c'),
                'version' => 1,
            ],
            'template_columns' => array_values($templateColumns),
            'mapping_status' => array_values($mappingStatus),
            'context' => $context,
        ];
    }

    /**
     * @param array<string, mixed> $passport
     * @return array<int, array{field:string,source:string,mapping_note:string,fill_note:string,example:string}>
     */
    public function buildInstructionRows(array $passport): array
    {
        $templateColumns = (array) ($passport['template_columns'] ?? []);
        $mappingStatus = (array) ($passport['mapping_status'] ?? []);
        $supplierCode = (string) (($passport['meta']['supplier_code'] ?? '') ?: '');
        $context = (array) ($passport['context'] ?? []);
        $liewoodRetail = (bool) ($context['liewood_retail'] ?? false);
        $exampleRow = is_array($context['example_row'] ?? null) ? (array) $context['example_row'] : [];

        $sourcesByTarget = [];
        foreach ($mappingStatus as $ms) {
            $target = trim((string) ($ms['target'] ?? ''));
            $source = trim((string) ($ms['source'] ?? ''));
            $status = (string) ($ms['status'] ?? '');
            if ($target === '' || $source === '' || $status === 'missing_source') {
                continue;
            }
            $sourcesByTarget[$target] ??= [];
            if (! in_array($source, $sourcesByTarget[$target], true)) {
                $sourcesByTarget[$target][] = $source;
            }
        }

        $rows = [];
        foreach ($templateColumns as $field) {
            $field = (string) $field;
            $sources = $sourcesByTarget[$field] ?? [];
            $sourceText = $sources === [] ? '—' : implode(' | ', $sources);
            $mappingNote = $sources === []
                ? 'Нет прямого маппинга'
                : 'Мапим с ' . implode(', ', $sources);
            $fillNote = $this->shortFillNote($supplierCode, $field, $sources, $liewoodRetail);
            $example = trim((string) ($exampleRow[$field] ?? ''));

            $rows[] = [
                'field' => $field,
                'source' => $sourceText,
                'mapping_note' => $mappingNote,
                'fill_note' => $fillNote,
                'example' => $example,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array{field:string,source:string,mapping_note:string,fill_note:string,example:string}> $rows
     */
    public function writeInstructionCsv(array $rows, string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException("Cannot write instruction CSV: {$path}");
        }

        $delimiter = ';';
        // Горизонтальный формат как в шаблоне: поля идут слева направо по колонкам.
        // Каждая строка — отдельный аспект инструкции.
        $fieldRow = ['Поле шаблона'];
        $sourceRow = ['Источник'];
        $fillRow = ['Как заполняется'];
        $exampleRow = ['Пример'];

        foreach ($rows as $r) {
            $fieldRow[] = $r['field'];
            $sourceRow[] = $r['source'];
            $exampleRow[] = $r['example'];
            $fillRow[] = $r['fill_note'];
        }

        fputcsv($fh, $fieldRow, $delimiter);
        fputcsv($fh, $sourceRow, $delimiter);
        fputcsv($fh, $fillRow, $delimiter);
        fputcsv($fh, $exampleRow, $delimiter);
        fclose($fh);
    }

    /**
     * @param array<string, mixed> $passport
     */
    public function writePassportJson(array $passport, string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($passport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<int, string> $sources
     */
    private function shortFillNote(string $supplierCode, string $field, array $sources, bool $liewoodRetail): string
    {
        if ($sources !== []) {
            return '—';
        }

        $fieldU = mb_strtoupper(trim($field));

        if ($supplierCode === 'liewood' && $liewoodRetail) {
            $map = [
                'TAGI PRODUKTU' => 'Ставим константу LIEWOOD',
                'PRODUCENT' => 'Ставим константу LIEWOOD',
                'NAZWA PRODUKTU (EN)' => 'Копируем из Product name (EN)',
                'NAZWA KATEGORII DOP POLE' => 'Копируем из item SubCategory',
                'SKU BRAND' => 'Копируем из Supplier product ID',
                'STYLE NO' => 'Копируем из Supplier product ID',
                'SIZE XS-XL' => 'Маршрутизация из Size/Type',
                'KG SIZE' => 'Маршрутизация из Size/Type',
                'AGE SIZE' => 'Маршрутизация из Size/Type',
                'HEIGHT SIZE' => 'Маршрутизация из Size/Type',
                'SOCKS SIZE' => 'Маршрутизация из Size/Type',
                'HATZ SIZE' => 'Маршрутизация из Size/Type',
                'SHOE SIZE' => 'Маршрутизация из Size/Type',
                'RECOMMENDED RETAIL PRICE EUR 2' => 'Дублируем Recommended Retail Price EUR',
                'WHOLESALE PRICE PLN' => 'Считаем из EUR по курсу',
                'WHOLESALE PRICE PLN 2' => 'Дублируем Wholesale Price PLN',
                'RECOMMEND RETAIL PRICE PLN' => 'Считаем из EUR по курсу',
                'RECOMMEND RETAIL PRICE PLN 2' => 'Дублируем Recommend Retail Price PLN',
            ];
            if (isset($map[$fieldU])) {
                return $map[$fieldU];
            }
            if (str_starts_with($fieldU, 'PHOTO ')) {
                return 'Заполняем URL фото по настроенному источнику';
            }
        }

        if ($fieldU === 'SKU' || $fieldU === 'SKU ') {
            return 'Из файла или генерируем по режиму бренда';
        }

        return 'Пусто, если нет правила или маппинга';
    }
}

