<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\Enums;

enum OperationStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'مجدول',
            self::Confirmed => 'مؤكد',
            self::InProgress => 'في غرفة العمليات',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغى',
            self::Postponed => 'مؤجّل',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Confirmed, self::Cancelled, self::Postponed],
            self::Confirmed => [self::InProgress, self::Cancelled, self::Postponed],
            self::InProgress => [self::Completed],
            // A postponed operation is rescheduled rather than resumed.
            self::Postponed => [self::Scheduled, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
