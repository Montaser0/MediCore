<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Encounters\Domain\Enums\TransferStatus;
use App\Modules\Encounters\Domain\Events\EncounterTransferred;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterTransferModel;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use Illuminate\Support\Facades\DB;

/**
 * Moves the same visit to another department — dental to radiology, clinic to
 * inpatient — without creating a second encounter, which is what keeps the
 * patient's history in one place.
 */
final class TransferEncounterAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(EncounterModel $encounter, array $data): EncounterTransferModel
    {
        if (! $encounter->isOpen()) {
            throw BusinessRuleException::make(
                'الزيارة مغلقة ولا يمكن نقلها.',
            );
        }

        $toDepartmentId = (int) $data['to_department_id'];

        if ($encounter->department_id === $toDepartmentId) {
            throw BusinessRuleException::make('المريض موجود في هذا القسم مسبقاً.');
        }

        return DB::transaction(function () use ($encounter, $data, $toDepartmentId): EncounterTransferModel {
            $fromDepartmentId = $encounter->department_id;

            $transfer = EncounterTransferModel::query()->create([
                'encounter_id' => $encounter->getKey(),
                'from_department_id' => $fromDepartmentId,
                'to_department_id' => $toDepartmentId,
                'from_user_id' => $this->currentUser->id(),
                'to_user_id' => $data['to_user_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'transferred_at' => now(),
                'status' => TransferStatus::Pending->value,
            ]);

            // The encounter follows the patient, so the receiving department's
            // queue picks it up immediately.
            $encounter->forceFill([
                'department_id' => $toDepartmentId,
                'clinic_id' => $data['clinic_id'] ?? null,
                'doctor_id' => $data['doctor_id'] ?? null,
            ])->save();

            $toDepartment = DepartmentModel::query()->find($toDepartmentId);

            EncounterTransferred::dispatch($encounter, $transfer);

            $this->timeline->record(
                patientId: (int) $encounter->patient_id,
                encounterId: $encounter->getKey(),
                eventType: 'encounter.transferred',
                title: 'نُقل إلى '.($toDepartment?->name ?? 'قسم آخر'),
                description: $data['reason'] ?? null,
                departmentId: $toDepartmentId,
            );

            $this->notifications->toDepartment(
                departmentId: $toDepartmentId,
                type: NotificationType::PatientTransferred->value,
                title: 'وصل مريض منقول',
                message: 'نُقل مريض إلى قسمكم.',
                entityType: EncounterModel::class,
                entityId: $encounter->getKey(),
            );

            return $transfer->load(['fromDepartment', 'toDepartment']);
        });
    }

    public function updateStatus(EncounterTransferModel $transfer, TransferStatus $target): EncounterTransferModel
    {
        if (! $transfer->status->canTransitionTo($target)) {
            throw BusinessRuleException::make(
                'لا يمكن تغيير حالة النقل.',
            );
        }

        $transfer->forceFill([
            'status' => $target->value,
            'to_user_id' => $transfer->to_user_id ?? $this->currentUser->id(),
        ])->save();

        return $transfer;
    }
}
