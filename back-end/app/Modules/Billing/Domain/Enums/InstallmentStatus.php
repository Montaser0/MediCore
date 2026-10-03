<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enums;

enum InstallmentStatus: string
{
    case Scheduled = 'scheduled';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'مجدول',
            self::Partial => 'مدفوع جزئياً',
            self::Paid => 'مدفوع',
        };
    }
}
