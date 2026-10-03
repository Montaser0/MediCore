<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PrescriptionItemModel extends BaseModel
{
    protected $table = 'prescription_items';

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'dispensed_quantity' => 'float',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(PrescriptionModel::class, 'prescription_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(MedicationModel::class, 'medication_id');
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(MedicationDispenseModel::class, 'prescription_item_id');
    }

    public function remainingQuantity(): float
    {
        if ($this->quantity === null) {
            return 0.0;
        }

        return max(0.0, (float) $this->quantity - (float) $this->dispensed_quantity);
    }

    /**
     * How the instruction reads on the label, e.g. "500 mg, twice daily, 7 days".
     */
    public function sig(): string
    {
        return implode(', ', array_filter([$this->dose, $this->frequency, $this->duration]));
    }
}
