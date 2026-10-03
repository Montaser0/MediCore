<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Application\Queries;

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
        return EncounterModel::query()
            ->with(['patient', 'department', 'clinic', 'doctor.staff'])
            ->forCurrentHospital()
            ->when(isset($filters['patient_id']), fn ($query) => $query->where('patient_id', $filters['patient_id']))
            ->when(isset($filters['department_id']), fn ($query) => $query->where('department_id', $filters['department_id']))
            ->when(isset($filters['clinic_id']), fn ($query) => $query->where('clinic_id', $filters['clinic_id']))
            ->when(isset($filters['doctor_id']), fn ($query) => $query->where('doctor_id', $filters['doctor_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(isset($filters['open_only']), fn ($query) => $query->open())
            ->when(isset($filters['date']), fn ($query) => $query->whereDate('created_at', $filters['date']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when(
                isset($filters['search']),
                fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('encounter_number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('patient', fn ($patient) => $patient->search((string) $filters['search'])))
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
