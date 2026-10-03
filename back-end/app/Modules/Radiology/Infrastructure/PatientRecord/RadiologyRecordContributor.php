<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use Illuminate\Database\Eloquent\Builder;

final class RadiologyRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'radiology';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $base = RadiologyOrderItemModel::query()->whereHas(
            'radiologyOrder.order.encounter',
            function (Builder $query) use ($patientId, $encounterId): void {
                $query->where('patient_id', $patientId);

                if ($encounterId !== null) {
                    $query->whereKey($encounterId);
                }
            },
        );

        $recent = (clone $base)
            ->with(['service', 'studies.report.verifiedBy', 'radiologyOrder.order.orderedBy'])
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->open()->count(),
            'reported' => (clone $base)->where('status', RadiologyItemStatus::Reported->value)->count(),
            'recent' => $recent->map(function (RadiologyOrderItemModel $item) {
                $study = $item->studies->last();

                return [
                    'id' => $item->getKey(),
                    'service' => $item->service?->name,
                    'price' => $item->service?->price,
                    'modality' => $item->service?->modality,
                    'status' => $item->status->value,
                    'order_number' => $item->radiologyOrder?->order?->order_number,
                    'by' => $item->radiologyOrder?->order?->orderedBy?->displayName(),
                    'verified_by' => $study?->report?->verifiedBy?->displayName(),
                    'study_id' => $study?->getKey(),
                    'study_number' => $study?->study_number,
                    'image_count' => $study?->image_count ?? 0,
                    'impression' => $study?->report?->impression,
                    'is_critical' => (bool) $study?->report?->is_critical,
                    'reported_at' => $study?->report?->verified_at?->toIso8601String(),
                ];
            })->all(),
        ];
    }
}
