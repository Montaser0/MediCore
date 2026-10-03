<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Insurance = 'insurance';
    case Other = 'other';

    /**
     * Card, transfer and insurance settlements carry an external reference
     * (authorisation code, transfer id, claim number).
     */
    public function requiresReference(): bool
    {
        return in_array($this, [self::Card, self::BankTransfer, self::Insurance], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقداً',
            self::Card => 'بطاقة',
            self::BankTransfer => 'تحويل مصرفي',
            self::Insurance => 'تأمين',
            self::Other => 'أخرى',
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
