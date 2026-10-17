<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
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
        $clinicId = $this->clinicForDepartment($toDepartmentId, $data['clinic_id'] ?? null);

        if ((int) $encounter->department_id === $toDepartmentId && (int) $encounter->clinic_id === (int) $clinicId) {
            throw BusinessRuleException::make('المريض موجود في هذه العيادة مسبقاً.');
        }

        return DB::transaction(function () use ($encounter, $data, $toDepartmentId, $clinicId): EncounterTransferModel {
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
                'clinic_id' => $clinicId,
                'doctor_id' => null,
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

            $this->notifyClinic($clinicId, $toDepartmentId, $encounter);

            return $transfer->load(['fromDepartment', 'toDepartment']);
        });
    }

    /**
     * A data-entry account only lists patients of its clinic. A department
     * transfer without a clinic would leave the visit invisible to that account.
     */
    private function clinicForDepartment(int $departmentId, mixed $requested): ?int
    {
        $hasClinics = ClinicModel::query()->where('department_id', $departmentId)->exists();

        if ($requested === null || $requested === '') {
            if ($hasClinics) {
                throw BusinessRuleException::make('اختر عيادة القسم.');
            }

            return null;
        }

        $clinicId = (int) $requested;
        $belongs = ClinicModel::query()
            ->whereKey($clinicId)
            ->where('department_id', $departmentId)
            ->exists();

        if (! $belongs) {
            throw BusinessRuleException::make('العيادة لا تتبع القسم المختار.');
        }

        return $clinicId;
    }

    private function notifyClinic(?int $clinicId, int $departmentId, EncounterModel $encounter): void
    {
        $title = 'وصل مريض منقول';
        $message = 'نُقل مريض إلى عيادتكم.';

        if ($clinicId === null) {
            $this->notifications->toDepartment(
                departmentId: $departmentId,
                type: NotificationType::PatientTransferred->value,
                title: $title,
                message: 'نُقل مريض إلى قسمكم.',
                entityType: EncounterModel::class,
                entityId: $encounter->getKey(),
            );

            return;
        }

        $userIds = UserModel::query()
            ->where(function ($query) use ($clinicId): void {
                $query->whereHas('staff', fn ($staff) => $staff->where('clinic_id', $clinicId))
                    ->orWhereHas('staff.doctor', fn ($doctor) => $doctor->inClinic($clinicId));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($userIds === []) {
            $this->notifications->toDepartment(
                departmentId: $departmentId,
                type: NotificationType::PatientTransferred->value,
                title: $title,
                message: 'نُقل مريض إلى قسمكم.',
                entityType: EncounterModel::class,
                entityId: $encounter->getKey(),
            );

            return;
        }

        $this->notifications->toUsers(
            userIds: $userIds,
            type: NotificationType::PatientTransferred->value,
            title: $title,
            message: $message,
            entityType: EncounterModel::class,
            entityId: $encounter->getKey(),
        );
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
