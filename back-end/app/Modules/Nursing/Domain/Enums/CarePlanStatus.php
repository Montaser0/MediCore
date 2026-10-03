<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Domain\Enums;

enum CarePlanStatus: string
{
    case Active = 'active';
    case Achieved = 'achieved';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعّال',
            self::Achieved => 'تحقق الهدف',
            self::Discontinued => 'متوقف',
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
