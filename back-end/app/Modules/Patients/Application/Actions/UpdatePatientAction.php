<?php

declare(strict_types=1);

namespace App\Modules\Patients\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Patients\Application\Queries\FindExistingPatientFile;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Support\Facades\DB;

final class UpdatePatientAction
{
    public function __construct(
        private readonly FindExistingPatientFile $existingFiles,
    ) {}

    /**
     * The MRN is deliberately not updatable: it is the key the whole archive
     * is filed under.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(PatientModel $patient, array $data): PatientModel
    {
        unset($data['mrn'], $data['hospital_id']);

        if (array_key_exists('national_id', $data)) {
            $nationalId = trim((string) $data['national_id']);
            $data['national_id'] = $nationalId === '' ? null : $nationalId;
        }

        $existing = $this->existingFiles->execute(
            hospitalId: (int) $patient->hospital_id,
            nationalId: array_key_exists('national_id', $data) ? $data['national_id'] : $patient->national_id,
            firstName: (string) ($data['first_name'] ?? $patient->first_name),
            lastName: (string) ($data['last_name'] ?? $patient->last_name),
            fatherName: array_key_exists('father_name', $data) ? $data['father_name'] : $patient->father_name,
            phone: array_key_exists('phone', $data) ? $data['phone'] : $patient->phone,
            dateOfBirth: array_key_exists('date_of_birth', $data) ? $data['date_of_birth'] : $patient->date_of_birth?->toDateString(),
            exceptId: $patient->getKey(),
        );

        if ($existing !== null) {
            throw BusinessRuleException::make(
                'هذه البيانات تطابق ملفاً طبياً آخر. أبقِ الزيارة على الملف الأصلي.',
                [
                    'patient_id' => $existing->getKey(),
                    'mrn' => $existing->mrn,
                ],
            );
        }

        return DB::transaction(function () use ($patient, $data): PatientModel {
            $patient->fill($data)->save();

            return $patient->fresh(['contacts', 'allergies', 'conditions']);
        });
    }
}
