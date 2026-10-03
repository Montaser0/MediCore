<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class LabOrderItemModel extends BaseModel
{
    use Auditable;

    protected $table = 'lab_order_items';

    protected function casts(): array
    {
        return [
            'status' => LabOrderItemStatus::class,
        ];
    }

    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrderModel::class, 'lab_order_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTestModel::class, 'lab_test_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(LabSampleModel::class, 'lab_order_item_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(LabResultModel::class, 'lab_order_item_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            LabOrderItemStatus::Verified->value,
            LabOrderItemStatus::Cancelled->value,
        ]);
    }
}
