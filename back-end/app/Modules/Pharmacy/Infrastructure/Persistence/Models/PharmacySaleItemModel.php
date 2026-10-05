<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PharmacySaleItemModel extends BaseModel
{
    protected $table = 'pharmacy_sale_items';

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'line_total' => 'float',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PharmacySaleModel::class, 'pharmacy_sale_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationModel::class, 'medication_id');
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(PharmacyReturnItemModel::class, 'pharmacy_sale_item_id');
    }
}
