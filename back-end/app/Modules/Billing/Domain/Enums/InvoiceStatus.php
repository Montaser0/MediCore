<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Issued, self::Cancelled],
            self::Issued => [self::PartiallyPaid, self::Paid, self::Cancelled],
            self::PartiallyPaid => [self::Paid, self::Cancelled],
            self::Paid, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function acceptsPayment(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid], true);
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Issued => 'صادر',
            self::PartiallyPaid => 'مدفوع جزئياً',
            self::Paid => 'مدفوع',
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
