<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Encounters\Domain\Enums\EncounterStatus;
use App\Modules\Encounters\Domain\Enums\EncounterType;
use App\Modules\Encounters\Domain\Events\EncounterCreated;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Support\Facades\DB;

/**
 * Opens a visit against an existing medical file. Reception uses this the
 * moment a patient arrives.
 */
final class CreateEncounterAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PatientModel $patient, array $data): EncounterModel
    {
        if (! $patient->status->acceptsNewEncounters()) {
            throw BusinessRuleException::make(
                'حالة المريض لا تسمح بفتح زيارة جديدة.',
                ['patient_id' => $patient->getKey(), 'status' => $patient->status->value],
            );
        }

        // A patient standing at the desk should not end up with two parallel
        // open visits in the same department.
        $existing = EncounterModel::query()
            ->open()
            ->where('patient_id', $patient->getKey())
            ->when(isset($data['department_id']), fn ($query) => $query->where('department_id', $data['department_id']))
            ->first();

        if ($existing !== null && ($data['allow_parallel'] ?? false) !== true) {
            throw BusinessRuleException::make(
                'توجد زيارة مفتوحة لهذا المريض في نفس القسم.',
                ['encounter_id' => $existing->getKey()],
            );
        }

        $type = EncounterType::from((string) ($data['type'] ?? EncounterType::Outpatient->value));

        return DB::transaction(function () use ($patient, $data, $type): EncounterModel {
            $encounter = EncounterModel::query()->create([
                'hospital_id' => $patient->hospital_id,
                'patient_id' => $patient->getKey(),
                'encounter_number' => $this->numbers->encounterNumber((int) $patient->hospital_id),
                'type' => $type->value,
                'status' => EncounterStatus::Waiting->value,
                'department_id' => $data['department_id'] ?? null,
                'clinic_id' => $data['clinic_id'] ?? null,
                'doctor_id' => $data['doctor_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'chief_complaint' => $data['chief_complaint'] ?? null,
                'created_by' => $this->currentUser->id(),
            ]);

            $encounter->statusHistory()->create([
                'from_status' => null,
                'to_status' => EncounterStatus::Waiting->value,
                'changed_by' => $this->currentUser->id(),
                'note' => 'فُتحت الزيارة',
            ]);

            EncounterCreated::dispatch($encounter);

            $this->timeline->record(
                patientId: $patient->getKey(),
                encounterId: $encounter->getKey(),
                eventType: 'encounter.created',
                title: 'فُتحت الزيارة',
                description: $this->visitKind($type),
                departmentId: $encounter->department_id,
            );

            return $encounter->fresh(['patient', 'department', 'clinic', 'doctor.staff']);
        });
    }

    private function visitKind(EncounterType $type): string
    {
        return match ($type) {
            EncounterType::Outpatient => 'عيادة خارجية',
            EncounterType::Emergency => 'طوارئ',
            EncounterType::Inpatient => 'داخلي',
            EncounterType::Operation => 'عملية',
            EncounterType::FollowUp => 'متابعة',
        };
    }
}
