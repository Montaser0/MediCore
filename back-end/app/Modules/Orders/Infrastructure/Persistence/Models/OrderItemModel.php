<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Orders\Domain\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItemModel extends BaseModel
{
    protected $table = 'order_items';

    protected function casts(): array
    {
        return [
            'status' => OrderItemStatus::class,
            'quantity' => 'float',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }
}
