<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MedicationDispenseModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'medication_dispenses';

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'total_price' => 'float',
            'batch_expiry_date' => 'date',
            'dispensed_at' => 'datetime',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(PrescriptionModel::class, 'prescription_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItemModel::class, 'prescription_item_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationModel::class, 'medication_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id');
    }

    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'dispensed_by');
    }
}
