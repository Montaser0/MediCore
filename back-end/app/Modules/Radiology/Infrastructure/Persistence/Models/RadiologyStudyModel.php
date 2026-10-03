<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Radiology\Domain\Enums\StudyStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class RadiologyStudyModel extends BaseModel
{
    use Auditable;

    protected $table = 'radiology_studies';

    protected function casts(): array
    {
        return [
            'status' => StudyStatus::class,
            'performed_at' => 'datetime',
            'image_count' => 'integer',
        ];
    }

    public function radiologyOrder(): BelongsTo
    {
        return $this->belongsTo(RadiologyOrderModel::class, 'radiology_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(RadiologyOrderItemModel::class, 'radiology_order_item_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'performed_by');
    }

    public function report(): HasOne
    {
        return $this->hasOne(RadiologyReportModel::class, 'study_id');
    }
}
