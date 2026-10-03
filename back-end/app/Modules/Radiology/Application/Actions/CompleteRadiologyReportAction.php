<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Core\Support\Contracts\OrderCoordinator;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use App\Modules\Radiology\Domain\Enums\ReportStatus;
use App\Modules\Radiology\Domain\Events\ReportCompleted;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyReportModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel;
use Illuminate\Support\Facades\DB;

/**
 * The radiologist dictates findings and impression. A draft can be saved and
 * revised; finalising it releases the report to the doctor and closes the
 * order item.
 */
final class CompleteRadiologyReportAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly ChangeRadiologyItemStatusAction $changeStatus,
        private readonly OrderCoordinator $orders,
        private readonly EncounterContext $encounters,
        private readonly TimelineRecorder $timeline,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(RadiologyStudyModel $study, array $data): RadiologyReportModel
    {
        $study->loadMissing(['report', 'orderItem.service', 'radiologyOrder.order']);

        if ($study->report?->isFinal() === true) {
            throw BusinessRuleException::make('لهذا الفحص تقرير نهائي مسبقاً.');
        }

        $finalise = (bool) ($data['finalise'] ?? false);

        return DB::transaction(function () use ($study, $data, $finalise): RadiologyReportModel {
            $report = RadiologyReportModel::query()->updateOrCreate(
                ['study_id' => $study->getKey()],
                [
                    'findings' => $data['findings'] ?? null,
                    'impression' => $data['impression'] ?? null,
                    'is_critical' => (bool) ($data['is_critical'] ?? false),
                    'reported_by' => $this->currentUser->id(),
                    'reported_at' => now(),
                    'status' => $finalise ? ReportStatus::Final->value : ReportStatus::Draft->value,
                    'verified_by' => $finalise ? $this->currentUser->id() : null,
                    'verified_at' => $finalise ? now() : null,
                ],
            );

            if ($finalise) {
                $this->release($study, $report);
            }

            return $report->load(['reportedBy', 'verifiedBy']);
        });
    }

    private function release(RadiologyStudyModel $study, RadiologyReportModel $report): void
    {
        $item = $study->orderItem;

        if ($item !== null && $item->status->canTransitionTo(RadiologyItemStatus::Reported)) {
            $this->changeStatus->execute($item, RadiologyItemStatus::Reported);
        }

        if ($item?->order_item_id !== null) {
            $this->orders->markItemCompleted((int) $item->order_item_id);
        }

        ReportCompleted::dispatch($report);

        $order = $study->radiologyOrder?->order;

        if ($order === null) {
            return;
        }

        $encounter = $this->encounters->snapshot((int) $order->encounter_id);
        $serviceName = $item?->service?->name ?? 'Imaging study';

        $this->timeline->record(
            patientId: $encounter->patientId,
            encounterId: $encounter->id,
            eventType: 'radiology.report_completed',
            title: 'تقرير أشعة: '.$serviceName,
            description: $report->impression,
            departmentId: $order->department_id,
            payload: [
                'radiology_report_id' => $report->getKey(),
                'study_id' => $study->getKey(),
                'is_critical' => $report->is_critical,
            ],
        );

        $this->notifications->toUsers(
            userIds: (int) $order->ordered_by,
            type: $report->is_critical ? 'radiology.critical_report' : 'radiology.report_ready',
            title: $report->is_critical ? 'نتيجة أشعة حرجة' : 'تقرير الأشعة جاهز',
            message: $serviceName.' تم إصدار التقرير',
            entityType: 'radiology_report',
            entityId: $report->getKey(),
            payload: ['study_id' => $study->getKey(), 'order_id' => $order->getKey()],
        );
    }
}
