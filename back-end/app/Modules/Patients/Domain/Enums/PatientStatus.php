<?php

declare(strict_types=1);

namespace App\Modules\Patients\Domain\Enums;

enum PatientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Deceased = 'deceased';

    /**
     * A deceased or inactive file must not accept new clinical activity.
     */
    public function acceptsNewEncounters(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
