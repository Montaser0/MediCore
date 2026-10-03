<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterFactory;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Inpatient\Domain\Enums\AdmissionStatus;
use App\Modules\Inpatient\Domain\Enums\DischargeType;
use App\Modules\Inpatient\Domain\Events\PatientDischarged;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use Illuminate\Support\Facades\DB;

/**
 * The other indivisible operation of the PRD: close the stay, free the bed,
 * close the visit and let billing settle the bed charges. Either all of it
 * happens or the patient is still admitted.
 */
final class DischargePatientAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly AssignBedAction $assignBed,
        private readonly EncounterFactory $encounters,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AdmissionModel $admission, array $data): AdmissionModel
    {
        if (! $admission->status->isActive()) {
            throw BusinessRuleException::make(
                "التنويم {$admission->admission_number} منتهٍ مسبقاً.",
                ['admission_id' => $admission->getKey()],
            );
        }

        if (! $admission->status->canTransitionTo(AdmissionStatus::Discharged)) {
            throw BusinessRuleException::make(
                "لا يمكن إخراج المريض والتنويم {$admission->admission_number} حالته «{$admission->status->label()}».",
            );
        }

        $type = DischargeType::from((string) ($data['discharge_type'] ?? DischargeType::Routine->value));

        // A discharge summary is part of the medical record, not paperwork to
        // be filled in later, so a routine discharge cannot go without one.
        if ($type === DischargeType::Routine && blank($data['discharge_summary'] ?? null)) {
            throw BusinessRuleException::make('الخروج العادي يحتاج ملخص خروج.');
        }

        return DB::transaction(function () use ($admission, $data, $type): AdmissionModel {
            $this->assignBed->release($admission);

            $admission->forceFill([
                'status' => AdmissionStatus::Discharged->value,
                'discharge_type' => $type->value,
                'discharge_summary' => $data['discharge_summary'] ?? null,
                'discharged_at' => $data['discharged_at'] ?? now(),
                'discharged_by' => $this->currentUser->id(),
            ])->save();

            // Billing listens for the event below and raises the bed charges
            // before the encounter is closed, so the final bill is complete.
            PatientDischarged::dispatch($admission);

            $this->encounters->close(
                (int) $admission->encounter_id,
                "خروج: {$type->label()}",
            );

            $this->timeline->record(
                patientId: (int) $admission->patient_id,
                encounterId: (int) $admission->encounter_id,
                eventType: 'inpatient.discharged',
                title: 'خروج من المستشفى',
                description: $type->label(),
                departmentId: $admission->department_id,
                payload: [
                    'admission_number' => $admission->admission_number,
                    'length_of_stay_days' => $admission->lengthOfStayDays(),
                ],
            );

            return $admission->fresh(['patient', 'bedAssignments.bed.room.ward']);
        });
    }
}
