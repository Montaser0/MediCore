<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Support;

use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetModel;

/**
 * In-store EAN-13 codes for technical assets. Prefix 22 is reserved here so
 * these labels never collide with pharmacy (20) or warehouse (21) barcodes.
 */
final class AssetBarcode
{
    public static function next(int $hospitalId): string
    {
        $sequence = self::highest($hospitalId) + 1;

        do {
            $body = '22'.str_pad((string) $sequence, 10, '0', STR_PAD_LEFT);
            $code = $body.self::checkDigit($body);
            $sequence++;
        } while (self::taken($hospitalId, $code));

        return $code;
    }

    public static function valid(string $code): bool
    {
        if (strlen($code) !== 13 || ! ctype_digit($code)) {
            return false;
        }

        return self::checkDigit(substr($code, 0, 12)) === $code[12];
    }

    private static function highest(int $hospitalId): int
    {
        $highest = 0;

        $codes = ItAssetModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('barcode', 'like', '22%')
            ->pluck('barcode');

        foreach ($codes as $code) {
            $code = (string) $code;

            if (strlen($code) === 13 && ctype_digit($code)) {
                $highest = max($highest, (int) substr($code, 2, 10));
            }
        }

        return $highest;
    }

    private static function taken(int $hospitalId, string $code): bool
    {
        return ItAssetModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('barcode', $code)
            ->exists();
    }

    private static function checkDigit(string $twelve): string
    {
        $sum = 0;

        for ($index = 0; $index < 12; $index++) {
            $digit = (int) $twelve[$index];
            $sum += $index % 2 === 0 ? $digit : $digit * 3;
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }
}
