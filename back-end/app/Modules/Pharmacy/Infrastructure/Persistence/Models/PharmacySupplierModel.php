<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PharmacySupplierModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'pharmacy_suppliers';

    public function purchases(): HasMany
    {
        return $this->hasMany(PharmacyPurchaseModel::class, 'pharmacy_supplier_id');
    }
}
