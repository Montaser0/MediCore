<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PharmacyStocktakeModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'pharmacy_stocktakes';

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PharmacyStocktakeLineModel::class, 'pharmacy_stocktake_id');
    }
}
