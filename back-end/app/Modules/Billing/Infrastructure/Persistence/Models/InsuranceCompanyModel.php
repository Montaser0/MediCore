<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InsuranceCompanyModel extends BaseModel
{
    protected $table = 'insurance_companies';

    protected function casts(): array
    {
        return [
            'default_coverage_percentage' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function policies(): HasMany
    {
        return $this->hasMany(PatientInsuranceModel::class, 'insurance_company_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
