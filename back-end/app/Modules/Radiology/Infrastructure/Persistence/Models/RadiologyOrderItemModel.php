<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RadiologyOrderItemModel extends BaseModel
{
    use Auditable;

    protected $table = 'radiology_order_items';

    protected function casts(): array
    {
        return [
            'status' => RadiologyItemStatus::class,
        ];
    }

    public function radiologyOrder(): BelongsTo
    {
        return $this->belongsTo(RadiologyOrderModel::class, 'radiology_order_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(RadiologyServiceModel::class, 'radiology_service_id');
    }

    public function studies(): HasMany
    {
        return $this->hasMany(RadiologyStudyModel::class, 'radiology_order_item_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            RadiologyItemStatus::Reported->value,
            RadiologyItemStatus::Cancelled->value,
        ]);
    }
}
