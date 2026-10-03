<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InvoiceItemModel extends BaseModel
{
    protected $table = 'invoice_items';

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'discount' => 'float',
            'total' => 'float',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceModel::class, 'invoice_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(ChargeModel::class, 'charge_id');
    }
}
