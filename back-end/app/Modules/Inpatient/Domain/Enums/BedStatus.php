<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Domain\Enums;

/**
 * A bed is a physical resource, so its status decides whether it can be
 * handed to the next patient.
 */
enum BedStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case Cleaning = 'cleaning';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'متاح',
            self::Occupied => 'مشغول',
            self::Reserved => 'محجوز',
            self::Cleaning => 'قيد التنظيف',
            self::Maintenance => 'قيد الصيانة',
        };
    }

    public function isAssignable(): bool
    {
        return in_array($this, [self::Available, self::Reserved], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Available => [self::Occupied, self::Reserved, self::Cleaning, self::Maintenance],
            self::Reserved => [self::Occupied, self::Available, self::Maintenance],
            // A bed is never handed straight from one patient to the next.
            self::Occupied => [self::Cleaning, self::Maintenance],
            self::Cleaning => [self::Available, self::Maintenance],
            self::Maintenance => [self::Available, self::Cleaning],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
