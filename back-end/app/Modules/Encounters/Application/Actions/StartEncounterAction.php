<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Encounters\Domain\Enums\EncounterStatus;
use App\Modules\Encounters\Domain\Events\EncounterStarted;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use Illuminate\Support\Facades\DB;

/**
 * The doctor pressing "Open Patient" on the clinic queue: the visit moves to
 * in_progress and, unless already set, the acting doctor takes ownership.
 */
final class StartEncounterAction
{
    public function __construct(
        private readonly ChangeEncounterStatusAction $changeStatus,
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
    ) {}

    public function execute(EncounterModel $encounter, ?int $doctorId = null): EncounterModel
    {
        return DB::transaction(function () use ($encounter, $doctorId): EncounterModel {
            $assignedDoctor = $doctorId ?? $encounter->doctor_id ?? $this->currentUser->doctorId();

            if ($assignedDoctor !== null && $encounter->doctor_id !== $assignedDoctor) {
                $encounter->forceFill(['doctor_id' => $assignedDoctor])->save();
            }

            $encounter = $this->changeStatus->execute($encounter, EncounterStatus::InProgress, 'Examination started');

            EncounterStarted::dispatch($encounter);

            $this->timeline->record(
                patientId: (int) $encounter->patient_id,
                encounterId: $encounter->getKey(),
                eventType: 'encounter.started',
                title: 'بدأت المعاينة',
                departmentId: $encounter->department_id,
            );

            return $encounter->fresh(['patient', 'department', 'clinic', 'doctor.staff']);
        });
    }
}
