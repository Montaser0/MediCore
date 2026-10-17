<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\EncounterSnapshot;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Clinical\Application\AccountDiagnosisClinics;
use App\Modules\Clinical\Domain\Enums\DiagnosisType;
use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisCodeModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AddDiagnosisAction
{
    public function __construct(
        private readonly EncounterContext $encounters,
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
        private readonly AccountDiagnosisClinics $accountClinics,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $encounterId, array $data): DiagnosisModel
    {
        $encounter = $this->encounters->requireOpen($encounterId);
        $type = DiagnosisType::from((string) ($data['type'] ?? DiagnosisType::Primary->value));

        $doctorId = $data['doctor_id'] ?? $encounter->doctorId ?? $this->currentUser->doctorId();

        if ($doctorId === null) {
            throw BusinessRuleException::make('التشخيص يجب أن يُنسب إلى طبيب.');
        }

        if (empty($data['diagnosis_code_id']) && trim((string) ($data['diagnosis_name'] ?? '')) !== '') {
            $data['diagnosis_code_id'] = $this->rememberForClinic($encounter, trim((string) $data['diagnosis_name']));
        }

        $this->assertAvailableForDoctor((int) $doctorId, $data);
        $name = $this->resolveName($data);

        return DB::transaction(function () use ($encounter, $data, $type, $doctorId, $name): DiagnosisModel {
            // Only one primary diagnosis per visit; an earlier one is demoted.
            if ($type === DiagnosisType::Primary) {
                DiagnosisModel::query()
                    ->where('encounter_id', $encounter->id)
                    ->where('type', DiagnosisType::Primary->value)
                    ->update(['type' => DiagnosisType::Secondary->value]);
            }

            $diagnosis = DiagnosisModel::query()->create([
                'encounter_id' => $encounter->id,
                'doctor_id' => $doctorId,
                'entered_by' => $this->currentUser->id(),
                'diagnosis_code_id' => $data['diagnosis_code_id'] ?? null,
                'diagnosis_name' => $name,
                'type' => $type->value,
                'notes' => $data['notes'] ?? null,
                'diagnosed_at' => isset($data['diagnosed_at']) ? Carbon::parse((string) $data['diagnosed_at']) : now(),
            ]);

            $this->timeline->record(
                patientId: $encounter->patientId,
                encounterId: $encounter->id,
                eventType: 'clinical.diagnosis_added',
                title: 'تشخيص '.$type->label().': '.$name,
                description: $data['notes'] ?? null,
                departmentId: $encounter->departmentId,
                payload: ['diagnosis_id' => $diagnosis->getKey()],
            );

            return $diagnosis->load(['code', 'doctor.staff', 'enteredBy.staff', 'enteredBy.roles']);
        });
    }

    /**
     * A name typed by the doctor or data-entry clerk becomes a catalog entry
     * for the visit's clinic, so it shows up with that clinic's diagnoses.
     */
    private function rememberForClinic(EncounterSnapshot $encounter, string $title): int
    {
        $clinicId = $this->clinicForCatalog($encounter);
        $existing = DiagnosisCodeModel::query()
            ->active()
            ->where('title', $title)
            ->whereHas('clinics', fn ($clinics) => $clinics->where('clinics.id', $clinicId))
            ->first();

        if ($existing !== null) {
            return (int) $existing->getKey();
        }

        $code = DiagnosisCodeModel::query()->create([
            'system' => 'local',
            'code' => DiagnosisCodeModel::nextLocalCode(),
            'title' => $title,
            'is_active' => true,
        ]);
        $code->clinics()->sync([$clinicId]);

        return (int) $code->getKey();
    }

    private function clinicForCatalog(EncounterSnapshot $encounter): int
    {
        $allowed = $this->accountClinics->ids();
        $visitClinic = $encounter->clinicId;

        if ($visitClinic !== null && ($allowed === null || in_array($visitClinic, $allowed, true))) {
            return $visitClinic;
        }

        if (is_array($allowed) && count($allowed) === 1) {
            return $allowed[0];
        }

        throw BusinessRuleException::make('لا يمكن إضافة التشخيص لأن الزيارة غير مربوطة بعيادة.');
    }

    /**
     * The picked diagnosis must belong to a clinic assigned to the diagnosing doctor.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertAvailableForDoctor(int $doctorId, array $data): void
    {
        $limited = $this->accountClinics->ids();
        $doctor = DoctorModel::query()->with('clinics:clinics.id')->find($doctorId);
        $clinicIds = $limited ?? ($doctor?->diagnosisClinicIds() ?? []);

        if ($clinicIds === []) {
            throw BusinessRuleException::make('هذا الطبيب غير مربوط بعيادة.');
        }

        $codeId = $data['diagnosis_code_id'] ?? null;
        $allowed = $codeId !== null && DiagnosisCodeModel::query()
            ->active()
            ->whereKey((int) $codeId)
            ->whereHas('clinics', fn ($clinics) => $clinics->whereIn('clinics.id', $clinicIds))
            ->exists();

        if ($allowed) {
            return;
        }

        $hasCatalog = DiagnosisCodeModel::query()->active()->forDoctor($doctorId)->exists();

        throw BusinessRuleException::make(
            $hasCatalog
                ? 'هذا التشخيص غير متاح في عيادة الطبيب.'
                : 'لا توجد تشخيصات مربوطة بعيادة هذا الطبيب.',
        );
    }

    /**
     * The doctor either picks a coded diagnosis or types a name; a coded pick
     * fills in the name so reports never show a blank label.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveName(array $data): string
    {
        if (! empty($data['diagnosis_name'])) {
            return (string) $data['diagnosis_name'];
        }

        if (! empty($data['diagnosis_code_id'])) {
            $code = DiagnosisCodeModel::query()->find($data['diagnosis_code_id']);

            if ($code !== null) {
                return (string) $code->title;
            }
        }

        throw BusinessRuleException::make('أدخل اسم التشخيص أو رمزاً صحيحاً.');
    }
}
