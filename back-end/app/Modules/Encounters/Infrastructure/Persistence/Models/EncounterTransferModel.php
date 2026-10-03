<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Encounters\Domain\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property TransferStatus $status
 */
final class EncounterTransferModel extends BaseModel
{
    protected $table = 'encounter_transfers';

    protected $casts = [
        'status' => TransferStatus::class,
        'transferred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EncounterModel, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    /**
     * @return BelongsTo<DepartmentModel, $this>
     */
    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'from_department_id');
    }

    /**
     * @return BelongsTo<DepartmentModel, $this>
     */
    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'to_department_id');
    }
}
