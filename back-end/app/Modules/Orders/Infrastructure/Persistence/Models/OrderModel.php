<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Orders\Domain\Enums\OrderItemStatus;
use App\Modules\Orders\Domain\Enums\OrderPriority;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Enums\OrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OrderModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'orders';

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'priority' => OrderPriority::class,
            'ordered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'ordered_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItemModel::class, 'order_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value]);
    }

    public function scopeOfType(Builder $query, OrderType|string $type): Builder
    {
        return $query->where('type', $type instanceof OrderType ? $type->value : $type);
    }

    /**
     * Department worklist ordering: stat first, then oldest request.
     */
    public function scopeWorklist(Builder $query): Builder
    {
        return $query
            ->orderByRaw("FIELD(priority, 'stat', 'urgent', 'routine')")
            ->orderBy('ordered_at');
    }

    public function hasOpenItems(): bool
    {
        return $this->items()
            ->whereIn('status', [OrderItemStatus::Pending->value, OrderItemStatus::InProgress->value])
            ->exists();
    }
}
