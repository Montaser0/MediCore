<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;

final class PharmacyDebtPaymentModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'pharmacy_debt_payments';

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_at' => 'datetime',
        ];
    }
}
