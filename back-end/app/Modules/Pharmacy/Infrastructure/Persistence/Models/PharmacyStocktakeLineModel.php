<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PharmacyStocktakeLineModel extends BaseModel
{
    protected $table = 'pharmacy_stocktake_lines';

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:2',
            'counted_quantity' => 'decimal:2',
        ];
    }

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(PharmacyStocktakeModel::class, 'pharmacy_stocktake_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationModel::class, 'medication_id');
    }
}
