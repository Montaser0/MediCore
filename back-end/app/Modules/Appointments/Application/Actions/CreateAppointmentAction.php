<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Appointments\Domain\Enums\AppointmentStatus;
use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CreateAppointmentAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AppointmentModel
    {
        $patient = PatientModel::query()->findOrFail((int) $data['patient_id']);

        $scheduledAt = Carbon::parse((string) $data['scheduled_at']);
        $duration = (int) ($data['duration_minutes'] ?? 15);

        if (isset($data['doctor_id'])) {
            $this->guardAgainstDoubleBooking((int) $data['doctor_id'], $scheduledAt, $duration);
        }

        return DB::transaction(function () use ($patient, $data, $scheduledAt, $duration): AppointmentModel {
            $appointment = AppointmentModel::query()->create([
                'hospital_id' => $patient->hospital_id,
                'patient_id' => $patient->getKey(),
                'appointment_number' => $this->numbers->appointmentNumber((int) $patient->hospital_id, $scheduledAt),
                'department_id' => $data['department_id'] ?? null,
                'clinic_id' => $data['clinic_id'] ?? null,
                'doctor_id' => $data['doctor_id'] ?? null,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $duration,
                'status' => AppointmentStatus::Scheduled->value,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $this->currentUser->id(),
            ]);

            $appointment->statusHistory()->create([
                'from_status' => null,
                'to_status' => AppointmentStatus::Scheduled->value,
                'changed_by' => $this->currentUser->id(),
                'note' => 'حُجز الموعد',
            ]);

            return $appointment->fresh(['patient', 'department', 'clinic', 'doctor.staff']);
        });
    }

    /**
     * A doctor cannot hold two overlapping appointments; the check uses the
     * requested duration rather than an exact timestamp match.
     */
    private function guardAgainstDoubleBooking(int $doctorId, Carbon $scheduledAt, int $duration): void
    {
        $endsAt = $scheduledAt->copy()->addMinutes($duration);

        $clash = AppointmentModel::query()
            ->active()
            ->where('doctor_id', $doctorId)
            ->whereDate('scheduled_at', $scheduledAt->toDateString())
            ->get()
            ->first(fn (AppointmentModel $existing) => $existing->scheduled_at < $endsAt && $existing->endsAt() > $scheduledAt);

        if ($clash !== null) {
            throw BusinessRuleException::make(
                'هذا الطبيب لديه موعد في الوقت نفسه.',
                [
                    'conflicting_appointment' => $clash->appointment_number,
                    'scheduled_at' => $clash->scheduled_at->toIso8601String(),
                ],
            );
        }
    }
}
