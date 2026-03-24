<?php

namespace App\Services\Import;

/**
 * Преобразует строки Liewood retail-шаблона: зеркала, константы, маршрутизация Size,
 * дубли EUR/PLN с курсом 4.25.
 */
final class LiewoodRetailTransformer
{
    public function __construct(
        private readonly SupplierTypeNormalizer $supplierTypeNormalizer,
    ) {
    }
    /**
     * @param  array<int, array<string, string>>  $rows  Уже смапленные retail-колонки
     * @param  array<int, array<string, string>>  $sourceRows  Нормализованные исходные строки (заголовки Liewood)
     * @param  list<string>  $dbFootwear  Доп. Type из настроек (верхний регистр)
     * @param  list<string>  $dbHat
     * @param  list<string>  $dbSocks
     * @return array<int, array<string, string>>
     */
    public function transform(
        array $rows,
        array $sourceRows,
        array $dbFootwear = [],
        array $dbHat = [],
        array $dbSocks = [],
    ): array {
        $rate = (float) config('liewood_retail.eur_to_pln_rate', 4.25);
        $constants = (array) config('liewood_retail.constants', []);
        $tagi = (string) ($constants['tagi_produktu'] ?? 'LIEWOOD');
        $producent = (string) ($constants['producent'] ?? 'LIEWOOD');

        $footwear = array_values(array_unique(array_merge(
            $this->upperList((array) config('liewood_retail.footwear_types', [])),
            $this->upperList($dbFootwear),
        )));
        $hats = array_values(array_unique(array_merge(
            $this->upperList((array) config('liewood_retail.hat_types', [])),
            $this->upperList($dbHat),
        )));
        $socksTypes = array_values(array_unique($this->upperList($dbSocks)));

        $sizeCols = array_values((array) config(
            'liewood_retail.size_route_columns',
            [
                'Size Xs-Xl',
                'KG size',
                'Age size',
                'height size',
                'socks size',
                'hatz size',
                'Shoe size',
            ],
        ));

        foreach ($rows as $i => $row) {
            $src = $sourceRows[$i] ?? [];

            $row['Tagi produktu'] = $tagi;
            $row['PRODUCENT'] = $producent;

            $name = trim((string) ($row['Product name (EN)'] ?? ''));
            if ($name !== '') {
                $row['Nazwa produktu (EN)'] = $name;
            }

            $sub = trim((string) ($row['item SubCategory'] ?? ''));
            if ($sub !== '') {
                $row['Nazwa kategorii dop pole'] = $sub;
            }

            $style = trim((string) ($row['Supplier product ID'] ?? ''));
            if ($style !== '') {
                $row['Sku Brand'] = $style;
                $row['Style no'] = $style;
            }

            $type = trim((string) ($src['Type'] ?? ''));
            $sizeRaw = trim((string) ($src['Size'] ?? ''));

            foreach ($sizeCols as $sc) {
                $row[$sc] = '';
            }

            $routed = $this->routeSize($sizeRaw, $type, $footwear, $hats, $socksTypes);
            foreach ($routed as $col => $val) {
                if ($val !== '') {
                    $row[$col] = $val;
                }
            }

            $wspEur = trim((string) ($row['Wholesale Price EUR'] ?? ''));
            $rrpEur = trim((string) ($row['Recommended Retail Price EUR'] ?? ''));

            $row['Recommended Retail Price EUR 2'] = $rrpEur;

            $wspPln = $this->formatPolishMoney($this->eurToPln($wspEur, $rate));
            $rrpPln = $this->formatPolishMoney($this->eurToPln($rrpEur, $rate));

            $row['Wholesale Price PLN'] = $wspPln;
            $row['Wholesale Price PLN 2'] = $wspPln;
            $row['Recommend Retail Price PLN'] = $rrpPln;
            $row['Recommend Retail Price PLN 2'] = $rrpPln;

            $rows[$i] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $footwearTypes
     * @param  array<int, string>  $hatTypes
     * @param  array<int, string>  $socksTypes  Доп. Type для socks (из настроек)
     * @return array<string, string>
     */
    private function routeSize(string $sizeRaw, string $type, array $footwearTypes, array $hatTypes, array $socksTypes): array
    {
        if ($sizeRaw === '') {
            return [];
        }

        $typeU = mb_strtoupper($type);
        $trimmed = trim($sizeRaw);
        $isFootwear = $this->typeMatchesCanonical($typeU, $footwearTypes);
        $isHat = $this->typeMatchesCanonical($typeU, $hatTypes);
        $isSocks = $this->typeMatchesCanonical($typeU, $socksTypes) || preg_match('/socks?/iu', $type) === 1;

        // 1) Буквенные / ONE SIZE → Size Xs-Xl
        if ($this->looksLikeLetterSize($trimmed)) {
            return ['Size Xs-Xl' => mb_strtoupper($trimmed)];
        }

        // 2) Вес (KG) → KG size
        if (preg_match('/KG/iu', $sizeRaw) === 1) {
            return ['KG size' => $trimmed];
        }

        // 3) Возраст: 1/2, 1-2Y, 12M и т.п. → Age size
        if ($this->looksLikeAgeSize($trimmed)) {
            return ['Age size' => $trimmed];
        }

        // 4) Рост в см: явно «cm» или число в типичном диапазоне роста (не обувь / не шапка / не носки)
        if (preg_match('/\d+\s*cm\b/iu', $sizeRaw) === 1 || preg_match('/\bcm\b/iu', $sizeRaw) === 1) {
            return ['height size' => $trimmed];
        }

        if (preg_match('/^\d{2,3}$/', $trimmed) === 1) {
            $n = (int) $trimmed;
            if ($n >= 50 && $n <= 116 && ! $isFootwear && ! $isHat && ! $isSocks) {
                return ['height size' => $trimmed];
            }
        }

        // 5) Носки по Type / названию типа
        if ($isSocks) {
            return ['socks size' => $trimmed];
        }

        // 6) Чистое число: обувь / шапка / остальное
        if (preg_match('/^\d+$/', $trimmed) === 1) {
            $n = (int) $trimmed;

            if ($isFootwear && $n >= 16 && $n <= 48) {
                return ['Shoe size' => $trimmed];
            }

            if ($isHat && $n >= 45 && $n <= 62) {
                return ['hatz size' => $trimmed];
            }

            if ($n >= 16 && $n <= 48) {
                return ['Shoe size' => $trimmed];
            }

            if ($n >= 45 && $n <= 62) {
                return ['hatz size' => $trimmed];
            }

            return ['Shoe size' => $trimmed];
        }

        return ['Age size' => $trimmed];
    }

    /**
     * Буквенные размеры и ONE SIZE для колонки Size Xs-Xl.
     */
    private function looksLikeLetterSize(string $s): bool
    {
        if (preg_match('/ONE\s*SIZE/iu', $s) === 1) {
            return true;
        }

        return preg_match(
            '/^(XXS|XXXL|XS|XL|XS\s*[-\/]\s*XL|[XSML]{1,4})$/iu',
            trim($s),
        ) === 1;
    }

    /**
     * Возрастные форматы для Age size.
     */
    private function looksLikeAgeSize(string $s): bool
    {
        if (preg_match('/\d\s*\/\s*\d/u', $s) === 1) {
            return true;
        }
        if (preg_match('/\d+\s*Y\b/iu', $s) === 1) {
            return true;
        }
        if (preg_match('/\d+\s*(M|MONTH|MONTHS)\b/iu', $s) === 1) {
            return true;
        }
        if (preg_match('/\d+\s*[-–]\s*\d+\s*(Y|M|MONTH|MONTHS)?\b/iu', $s) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Сопоставление Type с учётом канона (T-SHIRT = T SHIRT SLIM; ACTIVITY TOY не схлопывается с другими).
     *
     * @param  array<int, string>  $types
     */
    private function typeMatchesCanonical(string $typeU, array $types): bool
    {
        if ($typeU === '') {
            return false;
        }

        $needle = $this->supplierTypeNormalizer->canonicalForRouting($typeU);

        foreach ($types as $t) {
            $t = mb_strtoupper(trim((string) $t));
            if ($t === '') {
                continue;
            }
            if ($typeU === $t) {
                return true;
            }
            if ($needle !== '' && $needle === $this->supplierTypeNormalizer->canonicalForRouting($t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $list
     * @return array<int, string>
     */
    private function upperList(array $list): array
    {
        $out = [];
        foreach ($list as $x) {
            $out[] = mb_strtoupper(trim((string) $x));
        }

        return $out;
    }

    private function eurToPln(string $eurCell, float $rate): ?float
    {
        $f = $this->parseEuropeanFloat($eurCell);
        if ($f === null) {
            return null;
        }

        return round($f * $rate, 2);
    }

    private function parseEuropeanFloat(string $s): ?float
    {
        $s = trim(str_replace(["\xc2\xa0", ' '], '', $s));
        if ($s === '') {
            return null;
        }

        $s = str_replace(',', '.', $s);
        if (! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    private function formatPolishMoney(?float $v): string
    {
        if ($v === null) {
            return '';
        }

        if (abs($v - round($v)) < 0.00001) {
            return (string) (int) round($v);
        }

        $s = number_format($v, 2, ',', '');
        $s = rtrim(rtrim($s, '0'), ',');

        return $s;
    }
}
