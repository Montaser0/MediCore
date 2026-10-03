<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Domain\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Confirmed, self::CheckedIn, self::Cancelled, self::NoShow],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed, self::CheckedIn], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'مجدول',
            self::Confirmed => 'مؤكد',
            self::CheckedIn => 'حضر',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغى',
            self::NoShow => 'لم يحضر',
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
