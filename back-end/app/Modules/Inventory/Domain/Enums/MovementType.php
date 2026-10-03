<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Enums;

enum MovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Dispense = 'dispense';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';
    case Return = 'return';
    case Expired = 'expired';

    /**
     * Sign the movement applies to stock. An adjustment carries its own sign
     * in the quantity, so it is treated as neutral here.
     */
    public function direction(): int
    {
        return match ($this) {
            self::Purchase, self::TransferIn, self::Return => 1,
            self::Sale, self::Dispense, self::TransferOut, self::Expired => -1,
            self::Adjustment => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'استلام شراء',
            self::Sale => 'بيع',
            self::Dispense => 'صرف',
            self::TransferIn => 'تحويل وارد',
            self::TransferOut => 'تحويل صادر',
            self::Adjustment => 'تسوية',
            self::Return => 'إرجاع',
            self::Expired => 'إتلاف منتهي الصلاحية',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
