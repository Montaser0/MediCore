<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Support;

use App\Modules\Inventory\Domain\Enums\ItemType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;

/**
 * Internal EAN-13 codes. Pharmacy products start with 20 and general
 * warehouse products start with 21, which are the ranges reserved for
 * in-store barcodes.
 */
final class ItemBarcode
{
    public static function next(ItemType|string $kind): string
    {
        $prefix = self::pharmacy($kind) ? '20' : '21';
        $sequence = self::highest($prefix) + 1;

        do {
            $body = $prefix.str_pad((string) $sequence, 10, '0', STR_PAD_LEFT);
            $code = $body.self::checkDigit($body);
            $sequence++;
        } while (self::taken($code));

        return $code;
    }

    public static function fillMissing(): int
    {
        $count = 0;

        InventoryItemModel::query()
            ->where(fn ($query) => $query->whereNull('barcode')->orWhere('barcode', ''))
            ->orderBy('id')
            ->each(function (InventoryItemModel $item) use (&$count): void {
                $item->barcode = self::next($item->item_type);
                $item->save();
                $count++;
            });

        return $count;
    }

    public static function valid(string $code): bool
    {
        if (strlen($code) !== 13 || ! ctype_digit($code)) {
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

    private static function pharmacy(ItemType|string $kind): bool
    {
        if ($kind instanceof ItemType) {
            return $kind === ItemType::Medicine;
        }

        return in_array($kind, ['medicine', 'pharmacy'], true);
    }

    private static function highest(string $prefix): int
    {
        $highest = 0;

        foreach ([InventoryItemModel::class, MedicationModel::class] as $class) {
            $codes = $class::query()
                ->where('barcode', 'like', $prefix.'%')
                ->pluck('barcode');

            foreach ($codes as $code) {
                $highest = max($highest, (int) substr((string) $code, 2, 10));
            }
        }

        return $highest;
    }

    private static function taken(string $code): bool
    {
        return InventoryItemModel::query()->where('barcode', $code)->exists()
            || MedicationModel::query()->where('barcode', $code)->exists();
    }
}
