<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Enums;

enum BloodRequestStatus: string
{
    case Requested = 'requested';
    case Fulfilled = 'fulfilled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'مطلوب',
            self::Fulfilled => 'مؤكد',
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
