<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterFactory;
use App\Modules\Appointments\Domain\Enums\AppointmentStatus;
use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Illuminate\Support\Facades\DB;

/**
 * The moment a booked appointment becomes a live visit: it moves to
 * checked_in and an encounter is opened and linked back to it.
 */
final class CheckInAppointmentAction
{
    public function __construct(
        private readonly ChangeAppointmentStatusAction $changeStatus,
        private readonly EncounterFactory $encounters,
    ) {}

    public function execute(AppointmentModel $appointment): AppointmentModel
    {
        if ($appointment->encounter_id !== null) {
            return $appointment->fresh(['patient', 'department', 'clinic', 'doctor.staff']);
        }

        return DB::transaction(function () use ($appointment): AppointmentModel {
            $appointment = $this->changeStatus->execute(
                $appointment,
                AppointmentStatus::CheckedIn,
                'حضر المريض في الاستقبال',
            );

            $encounter = $this->encounters->openFor((int) $appointment->patient_id, [
                'type' => 'outpatient',
                'department_id' => $appointment->department_id,
                'clinic_id' => $appointment->clinic_id,
                'doctor_id' => $appointment->doctor_id,
                'reason' => $appointment->reason,
                'allow_parallel' => true,
            ]);

            $appointment->forceFill(['encounter_id' => $encounter->id])->save();

            return $appointment->fresh(['patient', 'department', 'clinic', 'doctor.staff']);
        });
    }
}
