<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EncounterStatusHistoryModel extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'encounter_status_history';

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EncounterModel, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }
}
