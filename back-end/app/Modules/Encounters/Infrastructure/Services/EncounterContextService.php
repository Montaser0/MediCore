<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Infrastructure\Services;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ResourceNotFoundException;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\EncounterSnapshot;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;

/**
 * The only door other modules use to reach encounter data.
 */
final class EncounterContextService implements EncounterContext
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    public function snapshot(int $encounterId): EncounterSnapshot
    {
        $encounter = EncounterModel::query()->find($encounterId);

        if ($encounter === null) {
            throw ResourceNotFoundException::for('Encounter', $encounterId);
        }

        $departmentId = $encounter->department_id === null ? null : (int) $encounter->department_id;
        $clinicId = $encounter->clinic_id === null ? null : (int) $encounter->clinic_id;

        if (! $this->currentUser->canAccessVisit($departmentId, $clinicId)) {
            throw BusinessRuleException::make('هذه الزيارة ليست من المرضى المحوّلين إلى عيادتك.');
        }

        return new EncounterSnapshot(
            id: $encounter->getKey(),
            encounterNumber: $encounter->encounter_number,
            patientId: (int) $encounter->patient_id,
            hospitalId: (int) $encounter->hospital_id,
            departmentId: $departmentId,
            clinicId: $clinicId,
            doctorId: $encounter->doctor_id === null ? null : (int) $encounter->doctor_id,
            type: $encounter->type->value,
            status: $encounter->status->value,
            isOpen: $encounter->isOpen(),
        );
    }

    public function requireOpen(int $encounterId): EncounterSnapshot
    {
        $snapshot = $this->snapshot($encounterId);

        if (! $snapshot->isOpen) {
            throw BusinessRuleException::make(
                "الزيارة {$snapshot->encounterNumber} مغلقة، ولا يمكن تسجيل إجراء جديد عليها.",
                ['encounter_id' => $snapshot->id, 'status' => $snapshot->status],
            );
        }

        return $snapshot;
    }
}
