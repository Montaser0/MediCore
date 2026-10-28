<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Support;

use App\Modules\Departments\Domain\Enums\DepartmentType;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;

/**
 * Who issued a lab request, frozen onto the slip: the visit's clinic and
 * doctor, or reception and the clerk when the request did not come from a clinic.
 */
final class LabOrderSource
{
    /**
     * @return array{clinic: ?string, doctor: ?string}
     */
    public static function names(OrderModel $order): array
    {
        $order->loadMissing([
            'encounter.clinic',
            'encounter.doctor.staff',
            'encounter.department',
            'orderedBy.staff',
        ]);

        return self::fromVisit($order->encounter, $order->orderedBy);
    }

    /**
     * @return array{clinic: ?string, doctor: ?string}
     */
    public static function fromVisit(?EncounterModel $encounter, ?UserModel $actor): array
    {
        $clinic = trim((string) ($encounter?->clinic?->name ?? ''));

        if ($clinic === '') {
            $clinic = $encounter?->department?->type === DepartmentType::Laboratory
                ? 'الاستقبال'
                : trim((string) ($encounter?->department?->name ?? ''));
        }

        $doctor = trim((string) ($encounter?->doctor?->staff?->full_name ?? ''));

        if ($doctor === '') {
            $doctor = trim((string) ($actor?->actorLabel() ?? ''));
        }

        return [
            'clinic' => self::clip($clinic),
            'doctor' => self::clip($doctor),
        ];
    }

    private static function clip(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 150);
    }
}
