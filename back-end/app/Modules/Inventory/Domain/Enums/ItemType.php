<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Enums;

enum ItemType: string
{
    case Medicine = 'medicine';
    case MedicalSupply = 'medical_supply';
    case Equipment = 'equipment';
    case General = 'general';

    /**
     * Medicines and supplies expire, so they need batch and expiry tracking;
     * equipment does not.
     */
    public function tracksExpiry(): bool
    {
        return in_array($this, [self::Medicine, self::MedicalSupply], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Medicine => 'دواء',
            self::MedicalSupply => 'مستلزم طبي',
            self::Equipment => 'تجهيزات',
            self::General => 'عام',
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
