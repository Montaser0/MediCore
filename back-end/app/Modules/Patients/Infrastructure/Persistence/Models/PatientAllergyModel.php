<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Patients\Domain\Enums\AllergySeverity;
use App\Modules\Patients\Domain\Enums\ClinicalRecordStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property AllergySeverity $severity
 * @property ClinicalRecordStatus $status
 */
final class PatientAllergyModel extends BaseModel
{
    use Auditable;

    protected $table = 'patient_allergies';

    protected $casts = [
        'severity' => AllergySeverity::class,
        'status' => ClinicalRecordStatus::class,
    ];

    /**
     * @return BelongsTo<PatientModel, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }

    /**
     * @param  Builder<PatientAllergyModel>  $query
     * @return Builder<PatientAllergyModel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ClinicalRecordStatus::Active->value);
    }
}
