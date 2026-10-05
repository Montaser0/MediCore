<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PharmacyCustomerModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'pharmacy_customers';

    public function sales(): HasMany
    {
        return $this->hasMany(PharmacySaleModel::class, 'pharmacy_customer_id');
    }
}
