<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MedicationCategoryModel extends BaseModel
{
    protected $table = 'medication_categories';

    protected function casts(): array
    {
        return [
            'is_controlled' => 'boolean',
        ];
    }

    public function medications(): HasMany
    {
        return $this->hasMany(MedicationModel::class, 'medication_category_id');
    }
}
