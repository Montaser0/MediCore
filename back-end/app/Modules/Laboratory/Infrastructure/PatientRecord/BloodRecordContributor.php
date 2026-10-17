<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel;

final class BloodRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'blood';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $base = BloodRequestModel::query()
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId));

        $recent = (clone $base)
            ->with(['requestedBy.staff', 'confirmedBy.staff'])
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'requested')->count(),
            'recent' => $recent->map(fn (BloodRequestModel $item) => [
                'id' => $item->getKey(),
                'product' => $item->product_name ?: $item->product_type,
                'blood_group' => $item->blood_group,
                'units' => $item->units,
                'unit_price' => $item->unit_price,
                'price' => round((float) $item->unit_price * (int) $item->units, 2),
                'status' => $item->status->value,
                'confirmed_blood_group' => $item->confirmed_blood_group,
                'bag_number' => $item->bag_number,
                'issued_at' => $item->issued_at?->toDateString(),
                'by' => $item->requestedBy?->actorLabel(),
                'confirmed_by' => $item->confirmedBy?->actorLabel(),
            ])->all(),
        ];
    }
}
