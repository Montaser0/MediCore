<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterFactory;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Inpatient\Domain\Enums\AdmissionStatus;
use App\Modules\Inpatient\Domain\Events\PatientAdmitted;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Support\Facades\DB;

/**
 * Admission is one indivisible operation (PRD §67): open the inpatient
 * encounter, number the admission, take the bed and tell the ward. If the bed
 * turns out to be taken, none of it happened.
 */
final class AdmitPatientAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
        private readonly EncounterFactory $encounters,
        private readonly AssignBedAction $assignBed,
        private readonly TimelineRecorder $timeline,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PatientModel $patient, array $data): AdmissionModel
    {
        $existing = AdmissionModel::query()
            ->active()
            ->where('patient_id', $patient->getKey())
            ->first();

        if ($existing !== null) {
            throw BusinessRuleException::make(
                'هذا المريض منوّم مسبقاً.',
                ['admission_id' => $existing->getKey()],
            );
        }

        return DB::transaction(function () use ($patient, $data): AdmissionModel {
            // An admission may continue an existing visit (typically the
            // emergency encounter) or open a fresh inpatient one.
            $encounterId = isset($data['encounter_id'])
                ? (int) $data['encounter_id']
                : $this->encounters->openFor($patient->getKey(), [
                    'type' => 'inpatient',
                    'department_id' => $data['department_id'] ?? null,
                    'doctor_id' => $data['attending_doctor_id'] ?? null,
                    'reason' => $data['admission_reason'] ?? null,
                    'allow_parallel' => true,
                ])->id;

            $admission = AdmissionModel::query()->create([
                'hospital_id' => $patient->hospital_id,
                'patient_id' => $patient->getKey(),
                'encounter_id' => $encounterId,
                'admission_number' => $this->numbers->admissionNumber((int) $patient->hospital_id),
                'department_id' => $data['department_id'] ?? null,
                'attending_doctor_id' => $data['attending_doctor_id'] ?? null,
                'admitted_by' => $this->currentUser->id(),
                'admitted_at' => $data['admitted_at'] ?? now(),
                'expected_discharge_at' => $data['expected_discharge_at'] ?? null,
                'status' => AdmissionStatus::Admitted->value,
                'admission_reason' => $data['admission_reason'] ?? null,
            ]);

            if (isset($data['bed_id'])) {
                $this->assignBed->execute($admission, (int) $data['bed_id'], 'Admission');
            }

            PatientAdmitted::dispatch($admission);

            $this->timeline->record(
                patientId: $patient->getKey(),
                encounterId: $encounterId,
                eventType: 'inpatient.admitted',
                title: 'تنويم',
                description: $admission->admission_reason,
                departmentId: $admission->department_id,
                payload: ['admission_number' => $admission->admission_number],
            );

            if ($admission->attending_doctor_id !== null) {
                $this->notifications->toUser(
                    userId: (int) $admission->attending_doctor_id,
                    type: 'inpatient.admitted',
                    title: 'تنويم جديد',
                    message: "{$patient->full_name} نُوّم في المستشفى.",
                    link: "/inpatient/admissions/{$admission->getKey()}",
                );
            }

            return $admission->fresh(['patient', 'currentBedAssignment.bed.room.ward', 'attendingDoctor']);
        });
    }
}
