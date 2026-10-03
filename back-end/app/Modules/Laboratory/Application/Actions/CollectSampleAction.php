<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\OrderCoordinator;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use App\Modules\Laboratory\Domain\Enums\SampleStatus;
use App\Modules\Laboratory\Domain\Events\SampleCollected;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabSampleModel;
use Illuminate\Support\Facades\DB;

final class CollectSampleAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
        private readonly ChangeLabItemStatusAction $changeStatus,
        private readonly OrderCoordinator $orders,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(LabOrderItemModel $item, array $data = []): LabSampleModel
    {
        $item->loadMissing(['test', 'labOrder.order']);

        if ($item->status === LabOrderItemStatus::Cancelled) {
            throw BusinessRuleException::make('هذا التحليل ملغى.');
        }

        $hospitalId = (int) ($item->labOrder?->order?->hospital_id ?? 0);

        if ($hospitalId === 0) {
            throw BusinessRuleException::make('طلب المختبر غير مرتبط بمستشفى.');
        }

        return DB::transaction(function () use ($item, $data, $hospitalId): LabSampleModel {
            $sample = LabSampleModel::query()->create([
                'lab_order_item_id' => $item->getKey(),
                'sample_number' => $this->numbers->sampleNumber($hospitalId),
                'sample_type' => $data['sample_type'] ?? $item->test?->sample_type,
                'collected_by' => $this->currentUser->id(),
                'collected_at' => now(),
                'status' => SampleStatus::Collected->value,
            ]);

            if ($item->status === LabOrderItemStatus::Pending) {
                $this->changeStatus->execute($item, LabOrderItemStatus::SampleCollected);
            }

            // Sample in hand means the laboratory has started work.
            $orderId = $item->labOrder?->order_id;

            if ($orderId !== null) {
                $this->orders->markInProgress((int) $orderId);
            }

            SampleCollected::dispatch($sample);

            return $sample;
        });
    }
}
