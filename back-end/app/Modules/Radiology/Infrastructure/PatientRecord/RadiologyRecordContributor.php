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
            ->with(['service', 'studies.report.verifiedBy', 'radiologyOrder.order.orderedBy.staff', 'radiologyOrder.order.orderedBy.roles'])
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $injectionOn = [];

        foreach ($recent as $item) {
            $orderId = (int) $item->radiology_order_id;
            $injection = (float) ($item->radiologyOrder?->injection_price ?? 0);

            if ($injection <= 0) {
                continue;
            }

            $injectionOn[$orderId] = min($injectionOn[$orderId] ?? $item->getKey(), $item->getKey());
        }

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->open()->count(),
            'reported' => (clone $base)->where('status', RadiologyItemStatus::Reported->value)->count(),
            'recent' => $recent->map(function (RadiologyOrderItemModel $item) use ($injectionOn) {
                $study = $item->studies->last();
                $orderId = (int) $item->radiology_order_id;
                $price = (float) ($item->service?->price ?? 0);

                if (($injectionOn[$orderId] ?? null) === $item->getKey()) {
                    $price += (float) ($item->radiologyOrder?->injection_price ?? 0);
                }

                return [
                    'id' => $item->getKey(),
                    'service' => $item->service?->name,
                    'with_injection' => (bool) $item->with_injection,
                    'price' => $price,
                    'modality' => $item->service?->modality,
                    'status' => $item->status->value,
                    'order_number' => $item->radiologyOrder?->order?->order_number,
                    'by' => $item->radiologyOrder?->order?->orderedBy?->actorLabel(),
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
