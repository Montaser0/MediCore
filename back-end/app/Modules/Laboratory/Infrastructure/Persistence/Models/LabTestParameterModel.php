<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LabTestParameterModel extends BaseModel
{
    protected $table = 'lab_test_parameters';

    protected function casts(): array
    {
        return [
            'normal_low' => 'float',
            'normal_high' => 'float',
            'critical_low' => 'float',
            'critical_high' => 'float',
            'display_order' => 'integer',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTestModel::class, 'lab_test_id');
    }
}
