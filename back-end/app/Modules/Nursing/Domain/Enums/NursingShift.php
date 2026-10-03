<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Domain\Enums;

use Illuminate\Support\Carbon;

enum NursingShift: string
{
    case Morning = 'morning';
    case Evening = 'evening';
    case Night = 'night';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'صباحي (07:00–15:00)',
            self::Evening => 'مسائي (15:00–23:00)',
            self::Night => 'ليلي (23:00–07:00)',
        };
    }

    /**
     * Saves the nurse from picking a shift that the clock already knows.
     */
    public static function at(?Carbon $moment = null): self
    {
        $hour = ($moment ?? now())->hour;

        return match (true) {
            $hour >= 7 && $hour < 15 => self::Morning,
            $hour >= 15 && $hour < 23 => self::Evening,
            default => self::Night,
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
