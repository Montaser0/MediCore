<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Laboratory\Domain\Enums\BloodRequestStatus;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BloodRequestModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'blood_requests';

    protected function casts(): array
    {
        return [
            'status' => BloodRequestStatus::class,
            'units' => 'integer',
            'unit_price' => 'float',
            'issued_at' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(BloodProductModel::class, 'blood_product_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'requested_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'confirmed_by');
    }
}
