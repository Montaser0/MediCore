<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Core\Support\Contracts\OrderCoordinator;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use App\Modules\Laboratory\Domain\Enums\ResultStatus;
use App\Modules\Laboratory\Domain\Events\LabResultVerified;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabResultModel;
use Illuminate\Support\Facades\DB;

/**
 * Verification is the release gate: only now does the result count as final,
 * reach the patient file and close the order item.
 */
final class VerifyLabResultAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly ChangeLabItemStatusAction $changeStatus,
        private readonly OrderCoordinator $orders,
        private readonly EncounterContext $encounters,
        private readonly TimelineRecorder $timeline,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function execute(LabResultModel $result): LabResultModel
    {
        $result->loadMissing(['orderItem.test', 'orderItem.labOrder.order']);

        $item = $result->orderItem;

        if ($item === null) {
            throw BusinessRuleException::make('هذه النتيجة غير مرتبطة بتحليل.');
        }

        if ($result->isVerified()) {
            throw BusinessRuleException::make('هذه النتيجة معتمدة مسبقاً.');
        }

        return DB::transaction(function () use ($result, $item): LabResultModel {
            $result->forceFill([
                'status' => ResultStatus::Final->value,
                'verified_by' => $this->currentUser->id(),
                'verified_at' => now(),
            ])->save();

            $this->changeStatus->execute($item, LabOrderItemStatus::Verified);

            if ($item->order_item_id !== null) {
                $this->orders->markItemCompleted((int) $item->order_item_id);
            }

            LabResultVerified::dispatch($result);

            $order = $item->labOrder?->order;

            if ($order !== null) {
                $encounter = $this->encounters->snapshot((int) $order->encounter_id);

                $this->timeline->record(
                    patientId: $encounter->patientId,
                    encounterId: $encounter->id,
                    eventType: 'laboratory.result_verified',
                    title: 'نتيجة تحليل: '.($item->test?->name ?? 'تحليل'),
                    description: match ($result->flag->value) {
                        'low' => 'النتيجة منخفضة',
                        'high' => 'النتيجة مرتفعة',
                        'critical' => 'نتيجة حرجة',
                        default => 'ضمن المجال المرجعي',
                    },
                    departmentId: $order->department_id,
                    payload: ['lab_result_id' => $result->getKey(), 'order_id' => $order->getKey()],
                );

                $this->notifications->toUsers(
                    userIds: (int) $order->ordered_by,
                    type: 'laboratory.result_verified',
                    title: 'نتيجة التحليل جاهزة',
                    message: ($item->test?->name ?? 'تحليل').' تم اعتمادها',
                    entityType: 'lab_result',
                    entityId: $result->getKey(),
                    payload: ['order_id' => $order->getKey(), 'flag' => $result->flag->value],
                );
            }

            return $result->load(['values.parameter', 'enteredBy', 'verifiedBy']);
        });
    }
}
