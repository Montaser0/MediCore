<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Nursing\Domain\Enums\AdministrationStatus;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionItemModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MedicationAdministrationModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'medication_administrations';

    protected function casts(): array
    {
        return [
            'status' => AdministrationStatus::class,
            'scheduled_at' => 'datetime',
            'administered_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItemModel::class, 'prescription_item_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationModel::class, 'medication_id');
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'administered_by');
    }

    /**
     * A dose is overdue once its scheduled time has passed and nobody has
     * accounted for it.
     */
    public function isOverdue(): bool
    {
        return $this->status === AdministrationStatus::Due
            && $this->scheduled_at !== null
            && $this->scheduled_at->isPast();
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', AdministrationStatus::Due->value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->due()->whereNotNull('scheduled_at')->where('scheduled_at', '<', now());
    }
}
