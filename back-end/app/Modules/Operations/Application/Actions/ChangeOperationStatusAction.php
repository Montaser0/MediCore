<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Operations\Domain\Enums\OperationRole;
use App\Modules\Operations\Domain\Enums\OperationStatus;
use App\Modules\Operations\Domain\Events\OperationCancelled;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of an operation's status, and therefore the only place the
 * pre-theatre checks can be bypassed — which is to say, nowhere.
 */
final class ChangeOperationStatusAction
{
    public function __construct(private readonly TimelineRecorder $timeline) {}

    public function execute(OperationModel $operation, OperationStatus $target, ?string $reason = null): OperationModel
    {
        $current = $operation->status;

        if ($current === $target) {
            return $operation;
        }

        if (! $current->canTransitionTo($target)) {
            throw BusinessRuleException::make(
                "لا يمكن تنفيذ هذا الإجراء والعملية {$operation->operation_number} حالتها «{$current->label()}».",
                ['operation_id' => $operation->getKey(), 'from' => $current->value, 'to' => $target->value],
            );
        }

        if ($target === OperationStatus::InProgress) {
            $this->guardReadyForTheatre($operation);
        }

        if (in_array($target, [OperationStatus::Cancelled, OperationStatus::Postponed], true) && blank($reason)) {
            throw BusinessRuleException::make('إلغاء العملية أو تأجيلها يحتاج سبباً.');
        }

        return DB::transaction(function () use ($operation, $target, $reason): OperationModel {
            $attributes = ['status' => $target->value];

            if ($target === OperationStatus::InProgress) {
                $attributes['started_at'] = now();
            }

            if (in_array($target, [OperationStatus::Cancelled, OperationStatus::Postponed], true)) {
                $attributes['cancellation_reason'] = $reason;
            }

            $operation->forceFill($attributes)->save();

            if ($target === OperationStatus::Cancelled) {
                OperationCancelled::dispatch($operation);
            }

            $this->timeline->record(
                patientId: (int) $operation->patient_id,
                encounterId: (int) $operation->encounter_id,
                eventType: 'operation.'.$target->value,
                title: 'عملية '.$target->label(),
                description: $reason ?? $operation->type?->name,
                payload: ['operation_number' => $operation->operation_number],
            );

            return $operation;
        });
    }

    /**
     * The surgical safety checklist, enforced rather than printed: consent on
     * file, a surgeon and an anaesthetist assigned.
     */
    private function guardReadyForTheatre(OperationModel $operation): void
    {
        if (! $operation->consent_obtained) {
            throw BusinessRuleException::make(
                "لم تُسجَّل موافقة المريض على العملية {$operation->operation_number}.",
                ['operation_id' => $operation->getKey()],
            );
        }

        $operation->loadMissing('team');

        foreach ([OperationRole::Surgeon, OperationRole::Anesthetist] as $role) {
            if (! $operation->team->contains('role', $role)) {
                throw BusinessRuleException::make(
                    "لا يوجد {$role->label()} معين للعملية {$operation->operation_number}.",
                    ['operation_id' => $operation->getKey(), 'missing_role' => $role->value],
                );
            }
        }
    }
}
