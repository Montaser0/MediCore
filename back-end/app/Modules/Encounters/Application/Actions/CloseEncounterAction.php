<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Actions;

use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Appointments\Application\Actions\ChangeAppointmentStatusAction;
use App\Modules\Appointments\Domain\Enums\AppointmentStatus;
use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use App\Modules\Encounters\Domain\Enums\EncounterStatus;
use App\Modules\Encounters\Domain\Events\EncounterCompleted;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use Illuminate\Support\Facades\DB;

/**
 * Discharge: records the closing details and completes the visit. Listeners
 * pick the event up to finalise billing and the timeline.
 */
final class CloseEncounterAction
{
    public function __construct(
        private readonly ChangeEncounterStatusAction $changeStatus,
        private readonly ChangeAppointmentStatusAction $appointments,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(EncounterModel $encounter, array $data = []): EncounterModel
    {
        return DB::transaction(function () use ($encounter, $data): EncounterModel {
            $encounter->forceFill(array_filter([
                'discharge_instructions' => $data['discharge_instructions'] ?? null,
                'discharge_disposition' => $data['discharge_disposition'] ?? null,
                'follow_up_date' => $data['follow_up_date'] ?? null,
            ], static fn (mixed $value) => $value !== null))->save();

            $target = ($data['cancel'] ?? false) === true
                ? EncounterStatus::Cancelled
                : EncounterStatus::Completed;

            $encounter = $this->changeStatus->execute(
                $encounter,
                $target,
                $data['note'] ?? ($target === EncounterStatus::Cancelled ? 'أُلغيت الزيارة' : 'أُغلق ملف الزيارة'),
            );

            if ($target === EncounterStatus::Completed) {
                EncounterCompleted::dispatch($encounter);
            }

            $this->settleBooking($encounter, $target);

            $this->timeline->record(
                patientId: (int) $encounter->patient_id,
                encounterId: $encounter->getKey(),
                eventType: 'encounter.'.$target->value,
                title: $target === EncounterStatus::Completed ? 'إنهاء الزيارة' : 'إلغاء الزيارة',
                description: $data['discharge_instructions'] ?? null,
                departmentId: $encounter->department_id,
                payload: ['duration_minutes' => $encounter->durationInMinutes()],
            );

            return $encounter->fresh(['patient', 'department', 'doctor.staff']);
        });
    }

    /** The booking ends when the visit ends. The clinic page owns the rest. */
    private function settleBooking(EncounterModel $encounter, EncounterStatus $target): void
    {
        $appointment = AppointmentModel::query()
            ->where('encounter_id', $encounter->getKey())
            ->first();

        if ($appointment === null) {
            return;
        }

        $next = $target === EncounterStatus::Cancelled
            ? AppointmentStatus::Cancelled
            : AppointmentStatus::Completed;

        if (! $appointment->status->canTransitionTo($next)) {
            return;
        }

        $this->appointments->execute(
            $appointment,
            $next,
            $next === AppointmentStatus::Completed ? 'أُغلقت الزيارة' : 'أُلغيت الزيارة',
        );
    }
}
