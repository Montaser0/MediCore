<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Domain\Enums;

enum NoteType: string
{
    case General = 'general';
    case History = 'history';
    case Examination = 'examination';
    case Progress = 'progress';
    case Discharge = 'discharge';

    public function label(): string
    {
        return match ($this) {
            self::General => 'ملاحظة عامة',
            self::History => 'قصة مرضية',
            self::Examination => 'فحص',
            self::Progress => 'ملاحظة متابعة',
            self::Discharge => 'ملخص خروج',
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
