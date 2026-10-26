<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Pharmacy\Domain\Enums\PrescriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PrescriptionModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'prescriptions';

    protected function casts(): array
    {
        return [
            'status' => PrescriptionStatus::class,
            'prescribed_at' => 'datetime',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorModel::class, 'doctor_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'entered_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItemModel::class, 'prescription_id');
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(MedicationDispenseModel::class, 'prescription_id');
    }

    public function scopeDispensable(Builder $query): Builder
    {
        return $query
            ->where('fulfillment', 'hospital')
            ->whereIn('status', [
                PrescriptionStatus::Active->value,
                PrescriptionStatus::PartiallyDispensed->value,
            ]);
    }

    /**
     * True when every line has had its full quantity handed over.
     */
    public function isFullyDispensed(): bool
    {
        return $this->items()
            ->whereNotNull('quantity')
            ->whereColumn('dispensed_quantity', '<', 'quantity')
            ->doesntExist();
    }
}
