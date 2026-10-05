<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PharmacyReturnItemModel extends BaseModel
{
    protected $table = 'pharmacy_return_items';

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'line_total' => 'float',
        ];
    }

    public function returnDocument(): BelongsTo
    {
        return $this->belongsTo(PharmacyReturnModel::class, 'pharmacy_return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PharmacySaleItemModel::class, 'pharmacy_sale_item_id');
    }
}
