<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Domain\Enums;

enum NursingNoteType: string
{
    case Admission = 'admission';
    case Progress = 'progress';
    case Handover = 'handover';
    case Incident = 'incident';
    case Discharge = 'discharge';

    public function label(): string
    {
        return match ($this) {
            self::Admission => 'تقييم الدخول',
            self::Progress => 'ملاحظة متابعة',
            self::Handover => 'تسليم الوردية',
            self::Incident => 'تقرير حادث',
            self::Discharge => 'ملاحظة الخروج',
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
