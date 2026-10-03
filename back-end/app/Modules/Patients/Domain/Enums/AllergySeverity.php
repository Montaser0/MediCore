<?php

declare(strict_types=1);

namespace App\Modules\Patients\Domain\Enums;

enum AllergySeverity: string
{
    case Mild = 'mild';
    case Moderate = 'moderate';
    case Severe = 'severe';
    case Unknown = 'unknown';

    /**
     * Severe allergies are surfaced in the patient header banner.
     */
    public function requiresAlert(): bool
    {
        return $this === self::Severe;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
