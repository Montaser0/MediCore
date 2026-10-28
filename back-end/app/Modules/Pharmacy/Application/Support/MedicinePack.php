<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Support;

/**
 * A box holds strips, a strip holds tablets. Stock is counted in tablets.
 * The pharmacist buys a box or a strip and sells a strip, a box, or a tablet.
 */
final class MedicinePack
{
    public const BOX = 'علبة';

    public const STRIP = 'شريط';

    public const TABLET = 'حبة';

    public static function normalize(string $unit): string
    {
        return match (trim($unit)) {
            self::STRIP => self::STRIP,
            self::TABLET => self::TABLET,
            default => self::BOX,
        };
    }

    public static function per(string $unit, int $stripsPerBox, int $tabletsPerStrip): int
    {
        $strips = max(1, $stripsPerBox);
        $tablets = max(1, $tabletsPerStrip);

        return match (self::normalize($unit)) {
            self::STRIP => $tablets,
            self::TABLET => 1,
            default => $strips * $tablets,
        };
    }

    public static function toBase(float $quantity, string $unit, int $stripsPerBox, int $tabletsPerStrip): float
    {
        return round($quantity * self::per($unit, $stripsPerBox, $tabletsPerStrip), 2);
    }

    /**
     * Strip and tablet prices fall back to an even split of the box price
     * until the pharmacist types their own.
     */
    public static function sellPrice(
        float $boxPrice,
        float $stripPrice,
        float $tabletPrice,
        string $unit,
        int $stripsPerBox,
        int $tabletsPerStrip,
    ): float {
        $strips = max(1, $stripsPerBox);
        $tablets = max(1, $tabletsPerStrip);
        $strip = $stripPrice > 0 ? $stripPrice : round($boxPrice / $strips, 2);
        $tablet = $tabletPrice > 0 ? $tabletPrice : round($strip / $tablets, 2);

        return match (self::normalize($unit)) {
            self::STRIP => $strip,
            self::TABLET => $tablet,
            default => round($boxPrice, 2),
        };
    }

    public static function costPerBase(float $unitCost, string $unit, int $stripsPerBox, int $tabletsPerStrip): float
    {
        $per = self::per($unit, $stripsPerBox, $tabletsPerStrip);

        return $per > 0 ? round($unitCost / $per, 2) : 0.0;
    }
}
