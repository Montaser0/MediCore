<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Domain\Enums;

enum DischargeType: string
{
    case Routine = 'routine';
    case AgainstAdvice = 'against_medical_advice';
    case Transfer = 'transfer_to_another_facility';
    case Death = 'death';
    case Absconded = 'absconded';

    public function label(): string
    {
        return match ($this) {
            self::Routine => 'خروج عادي',
            self::AgainstAdvice => 'خروج رغم النصيحة الطبية',
            self::Transfer => 'تحويل لمنشأة أخرى',
            self::Death => 'وفاة',
            self::Absconded => 'مغادرة دون إذن',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
