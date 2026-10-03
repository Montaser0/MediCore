<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Radiology\Domain\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RadiologyReportModel extends BaseModel
{
    use Auditable;

    protected $table = 'radiology_reports';

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'is_critical' => 'boolean',
            'reported_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(RadiologyStudyModel::class, 'study_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'reported_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'verified_by');
    }

    public function isFinal(): bool
    {
        return $this->status === ReportStatus::Final && $this->verified_at !== null;
    }
}
