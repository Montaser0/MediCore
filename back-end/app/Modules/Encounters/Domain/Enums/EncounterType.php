<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Domain\Enums;

enum EncounterType: string
{
    case Outpatient = 'outpatient';
    case Emergency = 'emergency';
    case Inpatient = 'inpatient';
    case Operation = 'operation';
    case FollowUp = 'follow_up';

    /**
     * Inpatient and operation visits are the ones that require a bed and are
     * measured in days rather than minutes.
     */
    public function requiresAdmission(): bool
    {
        return in_array($this, [self::Inpatient, self::Operation], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
