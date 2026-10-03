<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\OrderCoordinator;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use App\Modules\Radiology\Domain\Enums\StudyStatus;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel;
use Illuminate\Support\Facades\DB;

/**
 * The technician takes the patient in and starts imaging.
 */
final class StartStudyAction
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
        private readonly ChangeRadiologyItemStatusAction $changeStatus,
        private readonly OrderCoordinator $orders,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(RadiologyOrderItemModel $item, array $data = []): RadiologyStudyModel
    {
        $item->loadMissing(['service', 'radiologyOrder.order']);

        if ($item->status === RadiologyItemStatus::Cancelled) {
            throw BusinessRuleException::make('طلب الأشعة هذا ملغى.');
        }

        $hospitalId = (int) ($item->radiologyOrder?->order?->hospital_id ?? 0);

        if ($hospitalId === 0) {
            throw BusinessRuleException::make('طلب الأشعة غير مرتبط بمستشفى.');
        }

        return DB::transaction(function () use ($item, $data, $hospitalId): RadiologyStudyModel {
            $study = RadiologyStudyModel::query()->create([
                'radiology_order_id' => $item->radiology_order_id,
                'radiology_order_item_id' => $item->getKey(),
                'study_number' => $this->numbers->studyNumber($hospitalId),
                'accession_number' => $data['accession_number'] ?? null,
                'study_instance_uid' => $data['study_instance_uid'] ?? null,
                'performed_by' => $this->currentUser->id(),
                'performed_at' => now(),
                'technician_notes' => $data['technician_notes'] ?? null,
                'status' => StudyStatus::InProgress->value,
            ]);

            $this->changeStatus->execute($item, RadiologyItemStatus::InProgress);

            $orderId = $item->radiologyOrder?->order_id;

            if ($orderId !== null) {
                $this->orders->markInProgress((int) $orderId);
            }

            return $study;
        });
    }
}
