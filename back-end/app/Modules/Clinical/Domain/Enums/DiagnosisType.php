<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Domain\Enums;

enum DiagnosisType: string
{
    case Provisional = 'provisional';
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Provisional => 'أولي',
            self::Primary => 'رئيسي',
            self::Secondary => 'ثانوي',
            self::Final => 'نهائي',
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
