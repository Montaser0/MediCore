<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use Illuminate\Database\Eloquent\Builder;

final class PharmacyCashSessionModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'pharmacy_cash_sessions';

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'opening_float' => 'float',
            'closed_at' => 'datetime',
            'counted_cash' => 'float',
            'expected_cash' => 'float',
            'variance' => 'float',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }
};
