<?php

namespace App\Support;

/** E.164 normalisation with UAE rules. Phones are stored and compared normalised. */
final class Phone
{
    private const CALLING_CODES = ['AE' => '971', 'SA' => '966', 'OM' => '968', 'QA' => '974', 'BH' => '973', 'KW' => '965', 'EG' => '20'];

    public static function normalize(?string $raw, string $country = 'AE'): ?string
    {
        if ($raw === null) {
            return null;
        }
        $international = str_starts_with(trim($raw), '+');
        $d = self::digits($raw);
        if (strlen($d) < 8) {
            return null;                                   // unreadable or too short
        }
        $cc = self::CALLING_CODES[strtoupper($country)] ?? '971';
        if (str_starts_with($d, '00')) {                    // 00971… → +971…
            $d = substr($d, 2);
            $international = true;
        }
        if ($international || str_starts_with($d, $cc)) {
            if (str_starts_with($d, $cc.'0')) {              // +971 050… → +97150…
                $d = $cc.substr($d, strlen($cc) + 1);
            }

            return '+'.$d;
        }
        $d = ltrim($d, '0') === '' ? $d : preg_replace('/^0/', '', $d);   // 050… / 04… → national number

        return strlen($d) >= 8 ? '+'.$cc.$d : null;
    }

    public static function digits(?string $raw): string
    {
        return preg_replace('/\D+/', '', (string) $raw) ?? '';
    }
}
