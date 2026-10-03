<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LabOrderModel extends BaseModel
{
    protected $table = 'lab_orders';

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItemModel::class, 'lab_order_id');
    }
}
