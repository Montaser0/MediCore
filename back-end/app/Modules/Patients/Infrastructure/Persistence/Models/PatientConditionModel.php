<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Patients\Domain\Enums\ClinicalRecordStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ClinicalRecordStatus $status
 */
final class PatientConditionModel extends BaseModel
{
    use Auditable;

    protected $table = 'patient_conditions';

    protected $casts = [
        'status' => ClinicalRecordStatus::class,
        'diagnosed_at' => 'date',
    ];

    /**
     * @return BelongsTo<PatientModel, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }

    /**
     * @param  Builder<PatientConditionModel>  $query
     * @return Builder<PatientConditionModel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ClinicalRecordStatus::Active->value);
    }
}
