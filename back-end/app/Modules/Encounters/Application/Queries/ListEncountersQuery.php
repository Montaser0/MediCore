<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Queries;

use App\Core\Persistence\QueryWindow;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListEncountersQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, EncounterModel>
     */
    public function execute(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        return EncounterModel::query()
            ->forList()
            ->forCurrentHospital()
            ->when(isset($filters['patient_id']), fn ($query) => $query->where('encounters.patient_id', $filters['patient_id']))
            ->when(isset($filters['department_id']), fn ($query) => $query->where('encounters.department_id', $filters['department_id']))
            ->when(
                isset($filters['department_ids']) && is_array($filters['department_ids']),
                fn ($query) => $query->whereIn('encounters.department_id', $filters['department_ids']),
            )
            ->when(isset($filters['clinic_id']), fn ($query) => $query->where('encounters.clinic_id', $filters['clinic_id']))
            ->when(
                isset($filters['clinic_ids']) && is_array($filters['clinic_ids']),
                fn ($query) => $query->whereIn('encounters.clinic_id', $filters['clinic_ids']),
            )
            ->when(isset($filters['doctor_id']), fn ($query) => $query->where('encounters.doctor_id', $filters['doctor_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('encounters.status', $filters['status']))
            ->when(isset($filters['type']), fn ($query) => $query->where('encounters.type', $filters['type']))
            ->when(isset($filters['open_only']), fn ($query) => $query->open())
            ->when(isset($filters['date']), fn ($query) => QueryWindow::on($query, 'encounters.created_at', $filters['date']))
            ->when(isset($filters['from']), fn ($query) => QueryWindow::from($query, 'encounters.created_at', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => QueryWindow::until($query, 'encounters.created_at', $filters['to']))
            ->when(isset($filters['search']), fn ($query) => $query->search((string) $filters['search']))
            ->orderByDesc('encounters.created_at')
            ->paginate($perPage);
    }
}
