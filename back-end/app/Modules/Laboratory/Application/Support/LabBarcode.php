<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Support;

use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderModel;

/**
 * In-store EAN-13 codes. Prefix 23 is a lab order and 24 is a blood request,
 * so neither collides with pharmacy (20), warehouse (21) or assets (22).
 */
final class LabBarcode
{
    public static function next(): string
    {
        return self::allocate('23', LabOrderModel::class);
    }

    /** Blood requests use 24 so a lab scan never opens the wrong ticket. */
    public static function nextBlood(): string
    {
        return self::allocate('24', BloodRequestModel::class);
    }

    /**
     * @param  class-string<LabOrderModel|BloodRequestModel>  $model
     */
    private static function allocate(string $prefix, string $model): string
    {
        $sequence = self::highest($prefix, $model) + 1;

        do {
            $body = $prefix.str_pad((string) $sequence, 10, '0', STR_PAD_LEFT);
            $code = $body.self::checkDigit($body);
            $sequence++;
        } while ($model::query()->where('barcode', $code)->exists());

        return $code;
    }

    public static function valid(string $code): bool
    {
        $code = self::digits($code);

        if (strlen($code) !== 13) {
            return false;
        }

        return self::checkDigit(substr($code, 0, 12)) === $code[12];
    }

    public static function checkDigit(string $twelve): string
    {
        $sum = 0;

        for ($index = 0; $index < 12; $index++) {
            $digit = (int) $twelve[$index];
            $sum += $index % 2 === 0 ? $digit : $digit * 3;
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }

    public static function digits(string $value): string
    {
        $value = strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * @param  class-string<LabOrderModel|BloodRequestModel>  $model
     */
    private static function highest(string $prefix, string $model): int
    {
        $highest = 0;

        $codes = $model::query()
            ->where('barcode', 'like', $prefix.'%')
            ->pluck('barcode');

        foreach ($codes as $code) {
            $code = (string) $code;

            if (strlen($code) === 13 && ctype_digit($code)) {
                $highest = max($highest, (int) substr($code, 2, 10));
            }
        }

        return $highest;
    }
}
