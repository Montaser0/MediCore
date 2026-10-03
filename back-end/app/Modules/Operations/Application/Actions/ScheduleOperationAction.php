<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Operations\Domain\Enums\OperationRole;
use App\Modules\Operations\Domain\Enums\OperationStatus;
use App\Modules\Operations\Domain\Events\OperationScheduled;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTypeModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Books a theatre slot and assembles the team. Theatre and surgeon clashes
 * are caught here, because two operations in one room is not something the
 * schedule can recover from later.
 */
final class ScheduleOperationAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
        private readonly EncounterContext $encounters,
        private readonly TimelineRecorder $timeline,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): OperationModel
    {
        $encounter = $this->encounters->requireOpen((int) $data['encounter_id']);

        /** @var OperationTypeModel $type */
        $type = OperationTypeModel::query()->findOrFail($data['operation_type_id']);

        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $duration = (int) ($data['estimated_duration_minutes'] ?? $type->estimated_duration_minutes);
        $endsAt = $scheduledAt->copy()->addMinutes($duration);

        /** @var array<int, array<string, mixed>> $team */
        $team = $data['team'] ?? [];

        $this->guardTheatreIsFree($data['theatre_id'] ?? null, $scheduledAt, $endsAt);
        $this->guardTeamIsFree($team, $scheduledAt, $endsAt);

        return DB::transaction(function () use ($encounter, $data, $type, $scheduledAt, $duration, $team): OperationModel {
            $operation = OperationModel::query()->create([
                'hospital_id' => $encounter->hospitalId,
                'patient_id' => $encounter->patientId,
                'encounter_id' => $encounter->id,
                'operation_type_id' => $type->getKey(),
                'theatre_id' => $data['theatre_id'] ?? null,
                'operation_number' => $this->numbers->operationNumber($encounter->hospitalId),
                'status' => OperationStatus::Scheduled->value,
                'scheduled_at' => $scheduledAt,
                'estimated_duration_minutes' => $duration,
                'pre_op_diagnosis' => $data['pre_op_diagnosis'] ?? null,
                'anesthesia_type' => $data['anesthesia_type'] ?? null,
                'pre_op_notes' => $data['pre_op_notes'] ?? null,
                'created_by' => $this->currentUser->id(),
            ]);

            foreach ($team as $member) {
                $operation->team()->create([
                    'user_id' => (int) $member['user_id'],
                    'role' => (string) $member['role'],
                    'notes' => $member['notes'] ?? null,
                ]);
            }

            OperationScheduled::dispatch($operation);

            $this->timeline->record(
                patientId: $encounter->patientId,
                encounterId: $encounter->id,
                eventType: 'operation.scheduled',
                title: 'جدولة عملية',
                description: $type->name.' '.$scheduledAt->format('d/m/Y H:i'),
                payload: ['operation_number' => $operation->operation_number],
            );

            foreach ($team as $member) {
                $this->notifications->toUser(
                    userId: (int) $member['user_id'],
                    type: 'operation.scheduled',
                    title: 'أُضفت إلى فريق عملية',
                    message: $type->name.' '.$scheduledAt->format('d/m/Y H:i'),
                    link: "/operations/{$operation->getKey()}",
                );
            }

            return $operation->fresh(['type', 'theatre', 'team.user', 'patient']);
        });
    }

    private function guardTheatreIsFree(mixed $theatreId, Carbon $startsAt, Carbon $endsAt): void
    {
        if ($theatreId === null) {
            return;
        }

        $clash = OperationModel::query()
            ->holdingSlot()
            ->where('theatre_id', $theatreId)
            ->whereDate('scheduled_at', $startsAt->toDateString())
            ->get()
            ->first(fn (OperationModel $other) => $other->scheduled_at < $endsAt && $other->scheduledEndsAt() > $startsAt);

        if ($clash !== null) {
            throw BusinessRuleException::make(
                "غرفة العمليات محجوزة للعملية {$clash->operation_number} في هذا الوقت.",
                ['conflicting_operation_id' => $clash->getKey()],
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $team
     */
    private function guardTeamIsFree(array $team, Carbon $startsAt, Carbon $endsAt): void
    {
        // Only the people who must be physically present are checked; an
        // observer double-booked is not the system's problem.
        $criticalIds = collect($team)
            ->filter(fn (array $member) => OperationRole::tryFrom((string) $member['role'])?->isRequired() === true)
            ->pluck('user_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($criticalIds === []) {
            return;
        }

        $clash = OperationModel::query()
            ->holdingSlot()
            ->whereDate('scheduled_at', $startsAt->toDateString())
            ->whereHas('team', fn ($query) => $query->whereIn('user_id', $criticalIds))
            ->get()
            ->first(fn (OperationModel $other) => $other->scheduled_at < $endsAt && $other->scheduledEndsAt() > $startsAt);

        if ($clash !== null) {
            throw BusinessRuleException::make(
                "أحد أفراد الفريق مشغول في العملية {$clash->operation_number} في هذا الوقت.",
                ['conflicting_operation_id' => $clash->getKey()],
            );
        }
    }
}
