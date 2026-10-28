<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Support;

/**
 * Gross profit of a sold line: selling price after the invoice discount,
 * minus the cost of the batches that actually left, then the same share
 * reversed when the customer brings the goods back.
 */
final class PharmacyProfit
{
    public static function netOf(float $lineTotal, float $subtotal, float $saleTotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        return round($lineTotal * ($saleTotal / $subtotal), 2);
    }

    public static function onSold(float $lineTotal, float $subtotal, float $saleTotal, float $costTotal): float
    {
        return round(self::netOf($lineTotal, $subtotal, $saleTotal) - $costTotal, 2);
    }

    public static function onReturned(
        float $lineTotal,
        float $subtotal,
        float $saleTotal,
        float $costTotal,
        float $soldQty,
        float $returnedQty,
    ): float {
        if ($soldQty <= 0 || $returnedQty <= 0) {
            return 0.0;
        }

        $share = min($returnedQty / $soldQty, 1);

        return round((self::netOf($lineTotal, $subtotal, $saleTotal) - $costTotal) * $share, 2);
    }
};
