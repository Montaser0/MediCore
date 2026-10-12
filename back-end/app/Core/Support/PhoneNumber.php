<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * One stored form for phone lookup. Display keeps the number the desk typed;
 * search compares digits so 0991 234 567 and +963991234567 hit the same index.
 */
final class PhoneNumber
{
    public static function digits(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $latin = strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        return preg_replace('/\D+/', '', $latin) ?? '';
    }

    public static function normalize(?string $value): ?string
    {
        $digits = self::digits($value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits === '' ? null : $digits;
    }

    /**
     * Last 9 digits, so a local number and the same number with a country
     * code share one indexed value.
     */
    public static function tail(?string $value): ?string
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            return null;
        }

        return strlen($normalized) > 9 ? substr($normalized, -9) : $normalized;
    }
}
