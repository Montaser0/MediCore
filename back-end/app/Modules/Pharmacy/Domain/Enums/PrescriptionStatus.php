<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Enums;

enum PrescriptionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case PartiallyDispensed = 'partially_dispensed';
    case Dispensed = 'dispensed';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Cancelled],
            self::Active => [self::PartiallyDispensed, self::Dispensed, self::Cancelled],
            self::PartiallyDispensed => [self::Dispensed, self::Cancelled],
            self::Dispensed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Only an issued prescription may be dispensed against; a draft is still
     * being written and a cancelled one is void.
     */
    public function isDispensable(): bool
    {
        return in_array($this, [self::Active, self::PartiallyDispensed], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Active => 'فعّال',
            self::PartiallyDispensed => 'مصروف جزئياً',
            self::Dispensed => 'مصروف بالكامل',
            self::Cancelled => 'ملغى',
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
