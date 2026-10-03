<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PatientInsuranceModel extends BaseModel
{
    protected $table = 'patient_insurance';

    protected function casts(): array
    {
        return [
            'coverage_percentage' => 'float',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompanyModel::class, 'insurance_company_id');
    }

    /**
     * Cover only applies while the policy is in date.
     */
    public function scopeValidNow(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('is_active', true)
            ->where(fn (Builder $inner) => $inner->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn (Builder $inner) => $inner->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today));
    }
}
