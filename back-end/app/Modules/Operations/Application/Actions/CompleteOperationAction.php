<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Operations\Domain\Enums\OperationNoteType;
use App\Modules\Operations\Domain\Enums\OperationStatus;
use App\Modules\Operations\Domain\Events\OperationCompleted;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;
use Illuminate\Support\Facades\DB;

/**
 * Closes the operation with its operative note and post-operative plan. The
 * note is written here rather than promised for later, because an operation
 * without one is an incomplete medical record.
 */
final class CompleteOperationAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(OperationModel $operation, array $data): OperationModel
    {
        if ($operation->status !== OperationStatus::InProgress) {
            throw BusinessRuleException::make(
                "لا يمكن إنهاء العملية {$operation->operation_number} إلا وهي في غرفة العمليات، وحالتها الآن «{$operation->status->label()}».",
                ['operation_id' => $operation->getKey()],
            );
        }

        return DB::transaction(function () use ($operation, $data): OperationModel {
            $operation->forceFill([
                'status' => OperationStatus::Completed->value,
                'ended_at' => $data['ended_at'] ?? now(),
                'post_op_diagnosis' => $data['post_op_diagnosis'] ?? null,
                'post_op_instructions' => $data['post_op_instructions'] ?? null,
                'complications' => $data['complications'] ?? null,
                'outcome' => $data['outcome'] ?? 'successful',
                'follow_up_date' => $data['follow_up_date'] ?? null,
            ])->save();

            $operation->notes()->create([
                'note_type' => OperationNoteType::Operative->value,
                'content' => (string) $data['operative_note'],
                'created_by' => $this->currentUser->id(),
            ]);

            OperationCompleted::dispatch($operation);

            $this->timeline->record(
                patientId: (int) $operation->patient_id,
                encounterId: (int) $operation->encounter_id,
                eventType: 'operation.completed',
                title: 'اكتمال العملية',
                description: $operation->post_op_diagnosis ?? $operation->type?->name,
                payload: [
                    'operation_number' => $operation->operation_number,
                    'duration_minutes' => $operation->actualDurationMinutes(),
                    'complications' => filled($operation->complications),
                ],
            );

            return $operation->fresh(['type', 'team.user', 'notes.author', 'materials.item']);
        });
    }
}
