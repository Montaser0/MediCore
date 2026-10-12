<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Application\Queries;

use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class RadiologyWorklistQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return RadiologyOrderItemModel::query()
            ->with([
                'service',
                'studies.report',
                'radiologyOrder.order.encounter.patient',
                'radiologyOrder.order.orderedBy',
            ])
            ->whereHas('radiologyOrder.order', function ($query) use ($filters): void {
                $query->forCurrentHospital();

                if (! empty($filters['priority'])) {
                    $query->whereIn('priority', (array) $filters['priority']);
                }

                if (! empty($filters['patient_id'])) {
                    $query->whereHas('encounter', fn ($e) => $e->where('patient_id', $filters['patient_id']));
                }

                if (! empty($filters['encounter_id'])) {
                    $query->where('encounter_id', $filters['encounter_id']);
                }

                if (! empty($filters['date'])) {
                    $query->whereDate('ordered_at', $filters['date']);
                }
            })
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->whereIn('status', (array) $value))
            ->when($filters['radiology_service_id'] ?? null, fn ($q, $value) => $q->where('radiology_service_id', $value))
            ->when($filters['modality'] ?? null, fn ($q, $value) => $q->whereHas(
                'service',
                fn ($service) => $service->where('modality', $value),
            ))
            ->when(
                ($filters['open_only'] ?? null) !== null && filter_var($filters['open_only'], FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->open(),
            )
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
