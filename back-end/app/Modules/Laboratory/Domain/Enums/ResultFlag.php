<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Enums;

enum ResultFlag: string
{
    case Normal = 'normal';
    case Low = 'low';
    case High = 'high';
    case Critical = 'critical';

    /**
     * A critical value has to reach the treating doctor immediately (PRD:
     * critical result notification), not just sit in a report.
     */
    public function requiresImmediateNotification(): bool
    {
        return $this === self::Critical;
    }

    /**
     * Works out the flag for a numeric value against the parameter's ranges.
     * Non-numeric results (a culture description, say) stay normal and are
     * flagged manually by the technician.
     */
    public static function evaluate(
        mixed $value,
        ?float $normalLow,
        ?float $normalHigh,
        ?float $criticalLow = null,
        ?float $criticalHigh = null,
    ): self {
        if (! is_numeric($value)) {
            return self::Normal;
        }

        $value = (float) $value;

        if ($criticalLow !== null && $value <= $criticalLow) {
            return self::Critical;
        }

        if ($criticalHigh !== null && $value >= $criticalHigh) {
            return self::Critical;
        }

        if ($normalLow !== null && $value < $normalLow) {
            return self::Low;
        }

        if ($normalHigh !== null && $value > $normalHigh) {
            return self::High;
        }

        return self::Normal;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
