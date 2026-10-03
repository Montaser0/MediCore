<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Enums;

enum SampleStatus: string
{
    case Pending = 'pending';
    case Collected = 'collected';
    case Received = 'received';
    case Processing = 'processing';
    case Rejected = 'rejected';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Collected, self::Rejected],
            self::Collected => [self::Received, self::Rejected],
            self::Received => [self::Processing, self::Rejected],
            self::Processing => [self::Rejected],
            self::Rejected => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isUsable(): bool
    {
        return $this !== self::Rejected;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار السحب',
            self::Collected => 'سُحبت',
            self::Received => 'وصلت للمختبر',
            self::Processing => 'قيد المعالجة',
            self::Rejected => 'مرفوضة',
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
