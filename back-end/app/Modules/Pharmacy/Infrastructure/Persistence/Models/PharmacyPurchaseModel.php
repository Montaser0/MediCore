<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PharmacyPurchaseModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'pharmacy_purchases';

    protected function casts(): array
    {
        return [
            'total' => 'float',
            'cash_amount' => 'float',
            'due_amount' => 'float',
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(PharmacySupplierModel::class, 'pharmacy_supplier_id');
    }
}
