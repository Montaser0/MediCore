<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProcedureModel extends BaseModel
{
    use Auditable;

    protected $table = 'procedures';

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorModel::class, 'doctor_id');
    }
}
