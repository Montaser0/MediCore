<?php

declare(strict_types=1);

namespace App\Modules\Patients\Domain\Enums;

/**
 * Shared by allergies and chronic conditions, both of which are either still
 * relevant or historical.
 */
enum ClinicalRecordStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Resolved = 'resolved';
    case Unknown = 'unknown';

    /**
     * @return array<int, string>
     */
    public static function allergyValues(): array
    {
        return [self::Active->value, self::Inactive->value, self::Unknown->value];
    }

    /**
     * @return array<int, string>
     */
    public static function conditionValues(): array
    {
        return [self::Active->value, self::Resolved->value, self::Unknown->value];
    }
}
