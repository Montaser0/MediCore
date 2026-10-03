<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Laboratory\Domain\Enums\ResultFlag;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LabResultValueModel extends BaseModel
{
    protected $table = 'lab_result_values';

    protected function casts(): array
    {
        return [
            'flag' => ResultFlag::class,
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(LabResultModel::class, 'lab_result_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(LabTestParameterModel::class, 'lab_test_parameter_id');
    }
}
