<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Enums;

/**
 * The laboratory workflow from the PRD: a test is requested, a sample is
 * taken, the analyser runs, a technician enters the result and a supervisor
 * verifies it. Only a verified result is visible to the doctor as final.
 */
enum LabOrderItemStatus: string
{
    case Pending = 'pending';
    case SampleCollected = 'sample_collected';
    case Processing = 'processing';
    case ResultReady = 'result_ready';
    case Verified = 'verified';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::SampleCollected, self::Cancelled],
            self::SampleCollected => [self::Processing, self::ResultReady, self::Cancelled],
            self::Processing => [self::ResultReady, self::Cancelled],
            self::ResultReady => [self::Verified, self::Processing, self::Cancelled],
            self::Verified, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Verified, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار العينة',
            self::SampleCollected => 'سُحبت العينة',
            self::Processing => 'قيد المعالجة',
            self::ResultReady => 'أُدخلت النتيجة',
            self::Verified => 'معتمد',
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
