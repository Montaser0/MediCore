<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use App\Modules\Laboratory\Domain\Enums\ResultFlag;
use App\Modules\Laboratory\Domain\Enums\ResultStatus;
use App\Modules\Laboratory\Domain\Enums\SampleStatus;
use App\Modules\Laboratory\Domain\Exceptions\SampleNotReadyException;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabResultModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabTestParameterModel;
use Illuminate\Support\Facades\DB;

/**
 * A technician enters the result. It stays a draft until a supervisor
 * verifies it, but a critical value alerts the ordering doctor straight away
 * rather than waiting for verification.
 */
final class EnterLabResultAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly ChangeLabItemStatusAction $changeStatus,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(LabOrderItemModel $item, array $data): LabResultModel
    {
        $item->loadMissing(['test.parameters', 'samples', 'labOrder.order']);

        $this->requireUsableSample($item);

        return DB::transaction(function () use ($item, $data): LabResultModel {
            $result = LabResultModel::query()->updateOrCreate(
                ['lab_order_item_id' => $item->getKey()],
                [
                    'result_value' => $data['result_value'] ?? null,
                    'unit' => $data['unit'] ?? $item->test?->unit,
                    'reference_range' => $data['reference_range'] ?? $item->test?->reference_range,
                    'comment' => $data['comment'] ?? null,
                    'entered_by' => $this->currentUser->id(),
                    'entered_at' => now(),
                    'status' => ResultStatus::Draft->value,
                    'verified_by' => null,
                    'verified_at' => null,
                ],
            );

            $flags = $this->storeValues($result, $data['values'] ?? []);

            // The test-level flag is the worst of its parameters, or the flag
            // the technician chose for a single-value test.
            $flag = $this->overallFlag($data, $flags);

            $result->forceFill(['flag' => $flag->value])->save();

            if ($item->status->canTransitionTo(LabOrderItemStatus::ResultReady)) {
                $this->changeStatus->execute($item, LabOrderItemStatus::ResultReady);
            }

            if ($flag->requiresImmediateNotification()) {
                $this->alertCriticalResult($item, $result);
            } else {
                $this->alertOrderingDoctor($item, $result);
            }

            $this->notifyAwaitingVerification($item, $result);

            return $result->load(['values.parameter', 'enteredBy']);
        });
    }

    private function requireUsableSample(LabOrderItemModel $item): void
    {
        $usable = $item->samples
            ->first(fn ($sample) => in_array($sample->status, [
                SampleStatus::Collected,
                SampleStatus::Received,
                SampleStatus::Processing,
            ], true));

        if ($usable === null) {
            throw SampleNotReadyException::for($item->getKey());
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $values
     * @return array<int, ResultFlag>
     */
    private function storeValues(LabResultModel $result, array $values): array
    {
        if ($values === []) {
            return [];
        }

        $result->values()->delete();

        $flags = [];

        foreach ($values as $value) {
            $parameter = LabTestParameterModel::query()->find($value['lab_test_parameter_id'] ?? null);

            if ($parameter === null) {
                continue;
            }

            $flag = isset($value['flag'])
                ? ResultFlag::from((string) $value['flag'])
                : ResultFlag::evaluate(
                    $value['value'] ?? null,
                    $parameter->normal_low,
                    $parameter->normal_high,
                    $parameter->critical_low,
                    $parameter->critical_high,
                );

            $result->values()->create([
                'lab_test_parameter_id' => $parameter->getKey(),
                'value' => $value['value'] ?? null,
                'unit' => $value['unit'] ?? $parameter->unit,
                'reference_range' => $value['reference_range'] ?? $parameter->reference_range,
                'flag' => $flag->value,
            ]);

            $flags[] = $flag;
        }

        return $flags;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, ResultFlag>  $parameterFlags
     */
    private function overallFlag(array $data, array $parameterFlags): ResultFlag
    {
        if ($parameterFlags !== []) {
            foreach ([ResultFlag::Critical, ResultFlag::High, ResultFlag::Low] as $severity) {
                if (in_array($severity, $parameterFlags, true)) {
                    return $severity;
                }
            }

            return ResultFlag::Normal;
        }

        return isset($data['flag'])
            ? ResultFlag::from((string) $data['flag'])
            : ResultFlag::Normal;
    }

    private function alertCriticalResult(LabOrderItemModel $item, LabResultModel $result): void
    {
        $order = $item->labOrder?->order;

        if ($order === null) {
            return;
        }

        $order->loadMissing('encounter.patient');

        $this->notifications->toUsers(
            userIds: (int) $order->ordered_by,
            type: 'laboratory.critical_result',
            title: 'نتيجة تحليل حرجة',
            message: trim(($item->test?->name ?? 'تحليل').' — '.($order->encounter?->patient?->full_name ?? '')),
            entityType: 'lab_result',
            entityId: $result->getKey(),
            payload: [
                'lab_order_item_id' => $item->getKey(),
                'order_id' => $order->getKey(),
                'patient_id' => $order->encounter?->patient_id,
                'flag' => $result->flag->value,
            ],
        );
    }

    private function alertOrderingDoctor(LabOrderItemModel $item, LabResultModel $result): void
    {
        $order = $item->labOrder?->order;

        if ($order === null) {
            return;
        }

        $order->loadMissing('encounter.patient');

        $this->notifications->toUsers(
            userIds: (int) $order->ordered_by,
            type: 'laboratory.result_ready',
            title: 'وصلت نتيجة تحليل',
            message: trim(($item->test?->name ?? 'تحليل').' — '.($order->encounter?->patient?->full_name ?? '')),
            entityType: 'lab_result',
            entityId: $result->getKey(),
            payload: [
                'lab_order_item_id' => $item->getKey(),
                'order_id' => $order->getKey(),
                'patient_id' => $order->encounter?->patient_id,
            ],
        );
    }

    private function notifyAwaitingVerification(LabOrderItemModel $item, LabResultModel $result): void
    {
        $this->notifications->toPermissionHolders(
            permission: 'laboratory.results.verify',
            type: 'laboratory.result_awaiting_verification',
            title: 'نتيجة بانتظار الاعتماد',
            message: ($item->test?->name ?? 'تحليل').' جاهزة للاعتماد',
            entityType: 'lab_result',
            entityId: $result->getKey(),
            payload: ['lab_order_item_id' => $item->getKey(), 'flag' => $result->flag->value],
        );
    }
}
