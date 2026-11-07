<?php

namespace App\Support;

/** Owner invite / join codes: "<supplier id base36>-<6-char HMAC>", so a link only claims the shop it was made for. */
final class JoinCode
{
    public static function for(int $supplierId): string
    {
        $id = strtoupper(base_convert((string) $supplierId, 10, 36));

        return $id.'-'.self::sig($id);
    }

    public static function supplierId(?string $code): ?int
    {
        if (! $code || ! preg_match('/^([0-9A-Z]+)-([0-9A-Z]{6})$/', strtoupper(trim($code)), $m)) {
            return null;
        }

        return hash_equals(self::sig($m[1]), $m[2]) ? (int) base_convert($m[1], 36, 10) : null;
    }

    private static function sig(string $id): string
    {
        return strtoupper(substr(base_convert(substr(hash_hmac('sha256', 'join:'.$id, (string) config('app.key')), 0, 12), 16, 36), 0, 6));
    }
}
