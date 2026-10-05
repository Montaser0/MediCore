<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MedicationModel extends BaseModel
{
    protected $table = 'medications';

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'quantity' => 'float',
            'requires_prescription' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function categoryRecord(): BelongsTo
    {
        return $this->belongsTo(MedicationCategoryModel::class, 'medication_category_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItemModel::class, 'inventory_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', '%'.$term.'%')
                ->orWhere('generic_name', 'like', '%'.$term.'%')
                ->orWhere('barcode', $term);
        });
    }

    /**
     * What the doctor sees in the picker, e.g. "Amoxicillin 500 mg capsule".
     */
    public function displayName(): string
    {
        return trim(implode(' ', array_filter([$this->name, $this->strength, $this->dosage_form])));
    }
}
