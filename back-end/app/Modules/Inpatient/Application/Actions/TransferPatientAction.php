<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Inpatient\Domain\Enums\AdmissionStatus;
use App\Modules\Inpatient\Domain\Events\PatientTransferred;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use Illuminate\Support\Facades\DB;

/**
 * Moves a patient between beds, wards or departments. The old bed is only
 * released once the new one has been secured, so a failed transfer leaves the
 * patient where they were rather than nowhere.
 */
final class TransferPatientAction
{
    public function __construct(
        private readonly AssignBedAction $assignBed,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AdmissionModel $admission, array $data): AdmissionModel
    {
        if (! $admission->status->isActive()) {
            throw BusinessRuleException::make(
                "لا يمكن نقل التنويم {$admission->admission_number} لأنه «{$admission->status->label()}».",
                ['admission_id' => $admission->getKey()],
            );
        }

        $targetBedId = (int) $data['bed_id'];
        $currentAssignment = $admission->currentBedAssignment;

        if ($currentAssignment !== null && (int) $currentAssignment->bed_id === $targetBedId) {
            throw BusinessRuleException::make('المريض موجود في هذا السرير مسبقاً.');
        }

        return DB::transaction(function () use ($admission, $data, $targetBedId, $currentAssignment): AdmissionModel {
            $fromBed = $currentAssignment?->bed;

            $this->assignBed->release($admission);
            $admission->unsetRelation('currentBedAssignment');

            $assignment = $this->assignBed->execute(
                $admission,
                $targetBedId,
                $data['reason'] ?? 'Transfer',
            );

            $admission->forceFill(array_filter([
                'status' => AdmissionStatus::Transferred->value,
                'department_id' => $data['department_id'] ?? $admission->department_id,
                'attending_doctor_id' => $data['attending_doctor_id'] ?? $admission->attending_doctor_id,
            ], static fn ($value) => $value !== null))->save();

            /** @var BedModel $toBed */
            $toBed = $assignment->bed;

            PatientTransferred::dispatch($admission, $fromBed, $toBed);

            $this->timeline->record(
                patientId: (int) $admission->patient_id,
                encounterId: (int) $admission->encounter_id,
                eventType: 'inpatient.transferred',
                title: 'نقل داخلي',
                description: trim(($fromBed?->fullLabel() ?? 'بدون سرير').' → '.$toBed->fullLabel()),
                departmentId: $admission->department_id,
                payload: ['reason' => $data['reason'] ?? null],
            );

            return $admission->fresh(['currentBedAssignment.bed.room.ward']);
        });
    }
}
