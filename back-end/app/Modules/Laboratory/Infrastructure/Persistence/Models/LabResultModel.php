<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Laboratory\Domain\Enums\ResultFlag;
use App\Modules\Laboratory\Domain\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LabResultModel extends BaseModel
{
    use Auditable;

    protected $table = 'lab_results';

    protected function casts(): array
    {
        return [
            'flag' => ResultFlag::class,
            'status' => ResultStatus::class,
            'entered_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(LabOrderItemModel::class, 'lab_order_item_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(LabResultValueModel::class, 'lab_result_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'entered_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'verified_by');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null && $this->status === ResultStatus::Final;
    }
}
