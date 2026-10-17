<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;

final class OperationsRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'operations';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $operations = OperationModel::query()
            ->with(['type', 'team.user'])
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId))
            ->orderByDesc('scheduled_at')
            ->limit(30)
            ->get();

        return [
            'total' => $operations->count(),
            'items' => $operations->map(fn (OperationModel $operation) => [
                'id' => $operation->getKey(),
                'operation_number' => $operation->operation_number,
                'encounter_id' => $operation->encounter_id,
                'name' => $operation->type?->name,
                'price' => round(max(0, (float) ($operation->type?->price ?? 0) + (float) $operation->addition - (float) $operation->discount), 2),
                'discount' => $operation->discount,
                'addition' => $operation->addition,
                'status' => $operation->status->value,
                'scheduled_at' => $operation->scheduled_at?->toIso8601String(),
                'ended_at' => $operation->ended_at?->toIso8601String(),
                'surgeon' => $operation->surgeon()?->user?->displayName(),
                'by' => $operation->surgeon()?->user?->displayName(),
                'post_op_diagnosis' => $operation->post_op_diagnosis,
                'complications' => $operation->complications,
            ])->all(),
        ];
    }
}
