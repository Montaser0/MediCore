<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PharmacyReturnModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'pharmacy_returns';

    protected function casts(): array
    {
        return [
            'total' => 'float',
            'cash_refund' => 'float',
            'returned_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PharmacySaleModel::class, 'pharmacy_sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PharmacyReturnItemModel::class, 'pharmacy_return_id');
    }
}
