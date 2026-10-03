<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use Illuminate\Database\Eloquent\Builder;

final class LaboratoryRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'laboratory';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $base = LabOrderItemModel::query()->whereHas(
            'labOrder.order.encounter',
            function (Builder $query) use ($patientId, $encounterId): void {
                $query->where('patient_id', $patientId);

                if ($encounterId !== null) {
                    $query->whereKey($encounterId);
                }
            },
        );

        $recent = (clone $base)
            ->with(['test', 'result.verifiedBy', 'labOrder.order.orderedBy'])
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->open()->count(),
            'verified' => (clone $base)->where('status', LabOrderItemStatus::Verified->value)->count(),
            'recent' => $recent->map(fn (LabOrderItemModel $item) => [
                'id' => $item->getKey(),
                'test' => $item->test?->name,
                'price' => $item->test?->price,
                'status' => $item->status->value,
                'order_number' => $item->labOrder?->order?->order_number,
                'by' => $item->labOrder?->order?->orderedBy?->displayName(),
                'verified_by' => $item->result?->verifiedBy?->displayName(),
                'result_value' => $item->result?->result_value,
                'flag' => $item->result?->flag->value,
                'is_verified' => (bool) $item->result?->isVerified(),
                'reported_at' => $item->result?->verified_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
