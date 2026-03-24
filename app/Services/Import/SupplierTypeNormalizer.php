<?php

namespace App\Services\Import;

/**
 * Канонический ключ Type для сопоставления в импорте и дедупа в БД.
 *
 * ACTIVITY TOY / CREATIVE TOYS остаются отдельными сущностями; TOY не теряется.
 * T-SHIRT / T SHIRT / TSHIRT / T SHIRT SLIM → один ключ TSHIRT.
 */
final class SupplierTypeNormalizer
{
    public function canonicalForRouting(string $type): string
    {
        $t = mb_strtoupper(trim($type));
        if ($t === '') {
            return '';
        }

        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        if ($this->isToyPlayProtected($t)) {
            return $this->normalizeSeparatorsOnly($t);
        }

        $t = $this->normalizeSeparatorsOnly($t);
        $t = $this->stripTrailingModifiers($t);
        $t = $this->applyApparelPrefixCanonical($t);

        return $t;
    }

    /**
     * Только пробелы и разделители — для отображения/сравнения защищённых типов.
     */
    public function normalizeSeparatorsOnly(string $typeUpper): string
    {
        $t = mb_strtoupper(trim($typeUpper));
        $t = str_replace(['/', '-'], ' ', $t);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return trim($t);
    }

    /**
     * Игрушки/игры (ACTIVITY TOY, PUZZLE, …): не схлопываем с футболками и не режем «второе слово».
     */
    public function isToyOrPlaySemanticType(string $type): bool
    {
        $t = mb_strtoupper(trim($type));

        return $this->isToyPlayProtected($t);
    }

    private function isToyPlayProtected(string $typeUpper): bool
    {
        $toys = (array) config('supplier_type_normalization.toy_play_tokens', []);
        $pattern = $this->buildWordBoundaryPattern($toys);
        if ($pattern === null || preg_match($pattern, $typeUpper) !== 1) {
            return false;
        }

        $override = (array) config('supplier_type_normalization.apparel_override_tokens', []);
        $overridePattern = $this->buildWordBoundaryPattern($override);

        return $overridePattern === null || preg_match($overridePattern, $typeUpper) !== 1;
    }

    /**
     * @param  array<int, string>  $words
     */
    private function buildWordBoundaryPattern(array $words): ?string
    {
        $escaped = [];
        foreach ($words as $w) {
            $w = trim((string) $w);
            if ($w === '') {
                continue;
            }
            $escaped[] = preg_quote($w, '/');
        }
        if ($escaped === []) {
            return null;
        }

        return '/\b(?:'.implode('|', $escaped).')\b/u';
    }

    private function stripTrailingModifiers(string $t): string
    {
        $mods = (array) config('supplier_type_normalization.trailing_modifiers', []);
        usort($mods, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));
        $changed = true;
        while ($changed && $t !== '') {
            $changed = false;
            foreach ($mods as $mod) {
                $mod = trim((string) $mod);
                if ($mod === '') {
                    continue;
                }
                $suffix = ' '.$mod;
                if (mb_substr($t, -mb_strlen($suffix)) === $suffix) {
                    $t = trim(mb_substr($t, 0, -mb_strlen($suffix)));
                    $changed = true;

                    break;
                }
            }
        }

        return trim($t);
    }

    private function applyApparelPrefixCanonical(string $t): string
    {
        $groups = (array) config('supplier_type_normalization.apparel_prefix_groups', []);
        foreach ($groups as $group) {
            $canonical = mb_strtoupper(trim((string) ($group['canonical'] ?? '')));
            $prefixes = (array) ($group['prefixes'] ?? []);
            usort($prefixes, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));
            foreach ($prefixes as $prefix) {
                $p = $this->normalizeSeparatorsOnly((string) $prefix);
                if ($p === '') {
                    continue;
                }
                if ($t === $p) {
                    return $canonical;
                }
                $prefixWithSpace = $p.' ';
                if (str_starts_with($t, $prefixWithSpace)) {
                    return $canonical;
                }
            }
        }

        return $t;
    }
}
