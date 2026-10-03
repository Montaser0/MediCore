<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Nursing\Domain\Enums\CarePlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CarePlanModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'care_plans';

    protected function casts(): array
    {
        return [
            'status' => CarePlanStatus::class,
            'start_date' => 'date',
            'target_date' => 'date',
            'resolved_date' => 'date',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CarePlanStatus::Active->value);
    }
}
