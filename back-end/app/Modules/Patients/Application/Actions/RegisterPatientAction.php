<?php

declare(strict_types=1);

namespace App\Modules\Patients\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Patients\Application\DTOs\PatientData;
use App\Modules\Patients\Application\Queries\FindExistingPatientFile;
use App\Modules\Patients\Domain\Events\PatientRegistered;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Support\Facades\DB;

/**
 * Opens a permanent medical file: allocates the MRN and stores the baseline
 * medical information (contacts, allergies, chronic conditions) that reception
 * captures at the desk.
 */
final class RegisterPatientAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly TimelineRecorder $timeline,
        private readonly FindExistingPatientFile $existingFiles,
    ) {}

    public function execute(PatientData $data): PatientModel
    {
        $existing = $this->existingFiles->execute(
            hospitalId: $data->hospitalId,
            nationalId: $data->nationalId,
            firstName: $data->firstName,
            lastName: $data->lastName,
            fatherName: $data->fatherName,
            phone: $data->phone,
            dateOfBirth: $data->dateOfBirth,
        );

        if ($existing !== null) {
            throw BusinessRuleException::make(
                'هذا المريض مسجّل مسبقاً. أضف الزيارة الجديدة إلى سجله.',
                [
                    'patient_id' => $existing->getKey(),
                    'mrn' => $existing->mrn,
                ],
            );
        }

        return DB::transaction(function () use ($data): PatientModel {
            $patient = PatientModel::query()->create([
                ...$data->attributes(),
                'mrn' => $this->numbers->mrn($data->hospitalId),
            ]);

            foreach ($data->contacts as $contact) {
                $patient->contacts()->create($contact);
            }

            foreach ($data->allergies as $allergy) {
                $patient->allergies()->create($allergy);
            }

            foreach ($data->conditions as $condition) {
                $patient->conditions()->create($condition);
            }

            PatientRegistered::dispatch($patient);

            $this->timeline->record(
                patientId: $patient->getKey(),
                encounterId: null,
                eventType: 'patient.registered',
                title: 'تسجيل المريض',
                description: 'الرقم '.$patient->mrn,
            );

            return $patient->fresh(['contacts', 'allergies', 'conditions']);
        });
    }
}
