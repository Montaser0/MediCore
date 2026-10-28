<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PharmacySaleModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'pharmacy_sales';

    protected function casts(): array
    {
        return [
            'subtotal' => 'float',
            'discount' => 'float',
            'total' => 'float',
            'cash_amount' => 'float',
            'due_amount' => 'float',
            'sold_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PharmacySaleItemModel::class, 'pharmacy_sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PharmacyCustomerModel::class, 'pharmacy_customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Infrastructure\Persistence\Models\UserModel::class, 'user_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(HospitalModel::class, 'hospital_id');
    }
}
