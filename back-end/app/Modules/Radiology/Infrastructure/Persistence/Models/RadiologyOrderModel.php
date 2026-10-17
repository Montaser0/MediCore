<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RadiologyOrderModel extends BaseModel
{
    protected $table = 'radiology_orders';

    protected function casts(): array
    {
        return [
            'injection_price' => 'float',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RadiologyOrderItemModel::class, 'radiology_order_id');
    }

    public function studies(): HasMany
    {
        return $this->hasMany(RadiologyStudyModel::class, 'radiology_order_id');
    }
}
