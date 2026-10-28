<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Queries;

use App\Modules\Laboratory\Application\Support\LabBarcode;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class BloodRequestQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return BloodRequestModel::query()
            ->with(['patient', 'requestedBy.staff', 'confirmedBy.staff'])
            ->forCurrentHospital()
            ->when($filters['patient_id'] ?? null, fn ($query, $value) => $query->where('patient_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $term = '%'.trim((string) $search).'%';
                $digits = LabBarcode::digits((string) $search);
                $query->where(function ($inner) use ($term, $digits): void {
                    $inner->where('bag_number', 'like', $term)
                        ->orWhere('blood_group', 'like', $term)
                        ->orWhere('confirmed_blood_group', 'like', $term)
                        ->orWhere('product_name', 'like', $term)
                        ->orWhere('clinic_name', 'like', $term);
                    if ($digits !== '') {
                        $inner->orWhere('barcode', 'like', '%'.$digits.'%');
                    }
                    $inner->orWhereHas('patient', function ($patient) use ($term): void {
                        $patient->where('mrn', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('father_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term);
                    });
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
