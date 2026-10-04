<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Appointments\Application\Services\PublicBookingSettings;
use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use App\Modules\Patients\Application\Actions\RegisterPatientAction;
use App\Modules\Patients\Application\DTOs\PatientData;
use App\Modules\Patients\Application\Queries\FindExistingPatientFile;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BookPublicAppointmentAction
{
    public function __construct(
        private readonly FindExistingPatientFile $existingFiles,
        private readonly RegisterPatientAction $registerPatient,
        private readonly CreateAppointmentAction $createAppointment,
        private readonly PublicBookingSettings $booking,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AppointmentModel
    {
        $slot = $this->booking->assertBookable($data);
        $clinic = $slot['clinic'];
        $scheduledAt = $slot['scheduled_at'];
        $hospitalId = (int) $clinic->department->hospital_id;
        $lockKey = 'public-booking-'.$clinic->getKey().'-'.$scheduledAt->copy()->timezone(PublicBookingSettings::TIMEZONE)->toDateString();

        try {
            return Cache::lock($lockKey, 10)->block(5, function () use ($data, $slot, $clinic, $scheduledAt, $hospitalId): AppointmentModel {
                return DB::transaction(function () use ($data, $slot, $clinic, $scheduledAt, $hospitalId): AppointmentModel {
                    $this->booking->guardCapacity(
                        (int) $clinic->getKey(),
                        $scheduledAt,
                        (int) $slot['doctor_id'],
                        (int) $slot['capacity'],
                    );

                    $patient = $this->patient($data, $hospitalId);

                    $duplicate = AppointmentModel::query()
                        ->active()
                        ->where('patient_id', $patient->getKey())
                        ->where('clinic_id', $clinic->getKey())
                        ->whereBetween('scheduled_at', [
                            $scheduledAt->copy()->timezone(PublicBookingSettings::TIMEZONE)->startOfDay()->utc(),
                            $scheduledAt->copy()->timezone(PublicBookingSettings::TIMEZONE)->endOfDay()->utc(),
                        ])
                        ->exists();

                    if ($duplicate) {
                        throw BusinessRuleException::make('لديك موعد قائم في هذه العيادة في اليوم نفسه.');
                    }

                    return $this->createAppointment->execute([
                        'patient_id' => $patient->getKey(),
                        'department_id' => $clinic->department_id,
                        'clinic_id' => $clinic->getKey(),
                        'doctor_id' => $slot['doctor_id'],
                        'scheduled_at' => $scheduledAt->toIso8601String(),
                        'reason' => $this->blankToNull($data['reason'] ?? null),
                        'notes' => 'حجز عبر الاستمارة العامة',
                    ]);
                });
            });
        } catch (LockTimeoutException) {
            throw BusinessRuleException::make('تعذر تثبيت الموعد. أعد المحاولة.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function patient(array $data, int $hospitalId): PatientModel
    {
        $existing = $this->existingFiles->execute(
            hospitalId: $hospitalId,
            nationalId: $this->blankToNull($data['national_id'] ?? null),
            firstName: (string) $data['first_name'],
            lastName: (string) $data['last_name'],
            fatherName: $this->blankToNull($data['father_name'] ?? null),
            phone: (string) $data['phone'],
            dateOfBirth: $this->blankToNull($data['date_of_birth'] ?? null),
        );

        if ($existing !== null) {
            return $existing;
        }

        return $this->registerPatient->execute(PatientData::fromArray([
            'first_name' => $data['first_name'],
            'father_name' => $data['father_name'] ?? null,
            'last_name' => $data['last_name'],
            'gender' => $data['gender'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'phone' => $data['phone'],
        ], $hospitalId));
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
