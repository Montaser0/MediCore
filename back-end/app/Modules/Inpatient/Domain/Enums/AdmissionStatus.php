<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Domain\Enums;

enum AdmissionStatus: string
{
    case Admitted = 'admitted';
    case Transferred = 'transferred';
    case Discharged = 'discharged';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Admitted => 'منوّم',
            self::Transferred => 'محوّل',
            self::Discharged => 'خرج',
            self::Cancelled => 'ملغى',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Admitted, self::Transferred], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Admitted => [self::Transferred, self::Discharged, self::Cancelled],
            // A transfer keeps the stay open, so it can transfer again.
            self::Transferred => [self::Transferred, self::Discharged],
            self::Discharged, self::Cancelled => [],
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
