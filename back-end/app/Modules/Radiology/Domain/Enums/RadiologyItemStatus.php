<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Domain\Enums;

enum RadiologyItemStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Reported = 'reported';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Scheduled, self::InProgress, self::Cancelled],
            self::Scheduled => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed => [self::Reported, self::Cancelled],
            self::Reported, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Reported, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار التصوير',
            self::Scheduled => 'مجدول',
            self::InProgress => 'التصوير جارٍ',
            self::Completed => 'اكتملت الصور',
            self::Reported => 'صدر تقريره',
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
