<?php

namespace App\Support;

use Normalizer;

/** Name keys for brands, lines, products and companies (FR-23, FR-30). */
final class Names
{
    /** "Kérastase" → "kerastase", "No.4" → "no 4", "&honey" → "and honey"; Arabic letters kept. */
    public static function key(?string $s): string
    {
        $s = (string) $s;
        if (class_exists(Normalizer::class)) {
            $s = Normalizer::normalize($s, Normalizer::FORM_D) ?: $s;
        }
        $s = preg_replace('/\p{Mn}+/u', '', $s) ?? $s;
        $s = mb_strtolower(str_replace('&', ' and ', $s));
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    /** "250 ML" = "250ml". */
    public static function squash(?string $s): string
    {
        return str_replace(' ', '', self::key($s));
    }

    /** Company key: legal and trade words removed ("Gulf Beauty Trading L.L.C." → "gulf beauty"). */
    public static function company(?string $s): string
    {
        $k = ' '.self::key($s).' ';
        $k = preg_replace('/\s(l l c|llc|fze|fzc|fzco|fz|trading|trdg|general|est|establishment|co|company|ltd|group)(?=\s)/', ' ', $k) ?? $k;
        $k = preg_replace('/\s(l l c|llc)(?=\s)/', ' ', $k) ?? $k;

        return trim(preg_replace('/\s+/', ' ', $k) ?? $k);
    }

    /** First part of an area: "Naif, Deira, Dubai" → "naif". */
    public static function area(?string $s): string
    {
        return self::key(explode(',', (string) $s)[0]);
    }

    /** Areas match when either contains the other's first part ("Naif" vs "Naif, Deira"). */
    public static function sameArea(?string $a, ?string $b): bool
    {
        $x = self::key($a);
        $y = self::key($b);
        if ($x === '' || $y === '') {
            return false;
        }

        return str_contains($y, self::area($a)) || str_contains($x, self::area($b));
    }

    /** 0..1 similarity of two company names (for "near" candidates, FR-30). */
    public static function similarity(?string $a, ?string $b): float
    {
        $x = self::company($a);
        $y = self::company($b);
        if ($x === '' || $y === '') {
            return 0.0;
        }
        if ($x === $y) {
            return 1.0;
        }
        similar_text($x, $y, $pct);
        $lev = 1 - levenshtein($x, $y) / max(strlen($x), strlen($y));

        return round(max($pct / 100, $lev), 3);
    }
}
