<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Nursing\Domain\Enums\NursingNoteType;
use App\Modules\Nursing\Domain\Enums\NursingShift;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NursingNoteModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'nursing_notes';

    protected function casts(): array
    {
        return [
            'note_type' => NursingNoteType::class,
            'shift' => NursingShift::class,
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(AdmissionModel::class, 'admission_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }
}
