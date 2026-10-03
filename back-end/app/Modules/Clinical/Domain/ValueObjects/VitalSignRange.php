<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Domain\ValueObjects;

/**
 * Adult normal ranges, used to flag readings for the nurse or doctor rather
 * than to block them: an out-of-range vital sign is still recorded.
 */
final class VitalSignRange
{
    /**
     * @var array<string, array{0: float, 1: float}>
     */
    private const RANGES = [
        'temperature' => [36.1, 37.5],
        'systolic_bp' => [90, 140],
        'diastolic_bp' => [60, 90],
        'heart_rate' => [60, 100],
        'respiratory_rate' => [12, 20],
        'oxygen_saturation' => [95, 100],
    ];

    /**
     * @param  array<string, mixed>  $readings
     * @return array<int, array{field: string, value: float, normal_low: float, normal_high: float, direction: string}>
     */
    public static function abnormalities(array $readings): array
    {
        $flags = [];

        foreach (self::RANGES as $field => [$low, $high]) {
            $value = $readings[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $value = (float) $value;

            if ($value < $low || $value > $high) {
                $flags[] = [
                    'field' => $field,
                    'value' => $value,
                    'normal_low' => $low,
                    'normal_high' => $high,
                    'direction' => $value < $low ? 'low' : 'high',
                ];
            }
        }

        return $flags;
    }
}
