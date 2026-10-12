<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Clinical\Domain\Enums\DiagnosisType;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DiagnosisModel extends BaseModel
{
    use Auditable;

    protected $table = 'diagnoses';

    protected function casts(): array
    {
        return [
            'type' => DiagnosisType::class,
            'diagnosed_at' => 'datetime',
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

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'entered_by');
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(DiagnosisCodeModel::class, 'diagnosis_code_id');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereIn('type', [DiagnosisType::Primary->value, DiagnosisType::Final->value]);
    }
}
