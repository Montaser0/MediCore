<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Laboratory\Domain\Enums\SampleStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LabSampleModel extends BaseModel
{
    use Auditable;

    protected $table = 'lab_samples';

    protected function casts(): array
    {
        return [
            'status' => SampleStatus::class,
            'collected_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(LabOrderItemModel::class, 'lab_order_item_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'collected_by');
    }
}
