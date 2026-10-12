<?php

declare(strict_types=1);

namespace App\Modules\Patients\Application\Queries;

use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Core\Auth\CurrentUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Backs both the reception search box and the global search bar.
 */
final class SearchPatientsQuery
{
    public function __construct(private readonly CurrentUser $currentUser) {}
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PatientModel>
     */
    public function execute(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $doctorId = $this->currentUser->ownDoctorId();
        $clinicIds = $this->currentUser->dataEntryClinicIds();
        $departmentIds = $this->currentUser->dataEntryDepartmentIds();

        return PatientModel::query()
            ->forCurrentHospital()
            ->when(isset($filters['search']), fn ($query) => $query->search((string) $filters['search']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['gender']), fn ($query) => $query->where('gender', $filters['gender']))
            ->when(isset($filters['city']), fn ($query) => $query->where('city', $filters['city']))
            ->when(isset($filters['blood_type']), fn ($query) => $query->where('blood_type', $filters['blood_type']))
            ->when(
                isset($filters['registered_from']),
                fn ($query) => $query->whereDate('created_at', '>=', $filters['registered_from'])
            )
            ->when(
                isset($filters['registered_to']),
                fn ($query) => $query->whereDate('created_at', '<=', $filters['registered_to'])
            )
            ->when(
                ! empty($filters['department_id']),
                fn ($query) => $query->where(function ($inner) use ($filters): void {
                    $id = (int) $filters['department_id'];
                    $inner->whereHas(
                        'encounters',
                        fn ($encounters) => $encounters->where('department_id', $id)->where('status', '!=', 'cancelled'),
                    )->orWhereHas(
                        'appointments',
                        fn ($appointments) => $appointments->where('department_id', $id)->where('status', '!=', 'cancelled'),
                    );
                })
            )
            ->when(
                ! empty($filters['clinic_id']),
                fn ($query) => $query->where(function ($inner) use ($filters): void {
                    $id = (int) $filters['clinic_id'];
                    $inner->whereHas(
                        'encounters',
                        fn ($encounters) => $encounters->where('clinic_id', $id)->where('status', '!=', 'cancelled'),
                    )->orWhereHas(
                        'appointments',
                        fn ($appointments) => $appointments->where('clinic_id', $id)->where('status', '!=', 'cancelled'),
                    );
                })
            )
            ->with(['latestEncounter.department', 'latestEncounter.clinic'])
            ->when($clinicIds !== null, fn ($query) => $query->where(function ($inner) use ($clinicIds): void {
                $inner->whereHas(
                    'encounters',
                    fn ($encounters) => $encounters->whereIn('clinic_id', $clinicIds)->where('status', '!=', 'cancelled'),
                )->orWhereHas(
                    'appointments',
                    fn ($appointments) => $appointments->whereIn('clinic_id', $clinicIds)->where('status', '!=', 'cancelled'),
                );
            }))
            ->when($departmentIds !== null, fn ($query) => $query->where(function ($inner) use ($departmentIds): void {
                $inner->whereHas(
                    'encounters',
                    fn ($encounters) => $encounters->whereIn('department_id', $departmentIds)->where('status', '!=', 'cancelled'),
                )->orWhereHas(
                    'appointments',
                    fn ($appointments) => $appointments->whereIn('department_id', $departmentIds)->where('status', '!=', 'cancelled'),
                );
            }))
            ->when($doctorId !== null, fn ($query) => $query->where(function ($inner) use ($doctorId): void {
                $inner->whereHas(
                    'encounters',
                    fn ($encounters) => $encounters->where('doctor_id', $doctorId)->where('status', '!=', 'cancelled'),
                )->orWhereHas(
                    'appointments',
                    fn ($appointments) => $appointments->where('doctor_id', $doctorId)->where('status', '!=', 'cancelled'),
                );
            }))
            ->withCount('encounters')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
