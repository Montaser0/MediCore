<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\Enums;

enum OperationNoteType: string
{
    case PreOperative = 'pre_operative';
    case Anesthesia = 'anesthesia';
    case Operative = 'operative';
    case PostOperative = 'post_operative';
    case Recovery = 'recovery';

    public function label(): string
    {
        return match ($this) {
            self::PreOperative => 'ملاحظة ما قبل العملية',
            self::Anesthesia => 'سجل التخدير',
            self::Operative => 'ملاحظة العملية',
            self::PostOperative => 'ملاحظة ما بعد العملية',
            self::Recovery => 'ملاحظة الإفاقة',
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
