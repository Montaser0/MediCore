<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Operations\Domain\Enums\AnesthesiaType;
use App\Modules\Operations\Domain\Enums\OperationRole;
use App\Modules\Operations\Domain\Enums\OperationStatus;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OperationModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'operations';

    protected function casts(): array
    {
        return [
            'status' => OperationStatus::class,
            'anesthesia_type' => AnesthesiaType::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'consent_obtained' => 'boolean',
            'consent_obtained_at' => 'datetime',
            'follow_up_date' => 'date',
            'estimated_duration_minutes' => 'integer',
            'discount' => 'float',
            'addition' => 'float',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(OperationTypeModel::class, 'operation_type_id');
    }

    public function theatre(): BelongsTo
    {
        return $this->belongsTo(OperationTheatreModel::class, 'theatre_id');
    }

    public function team(): HasMany
    {
        return $this->hasMany(OperationTeamModel::class, 'operation_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OperationNoteModel::class, 'operation_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(OperationMaterialModel::class, 'operation_id');
    }

    public function surgeon(): ?OperationTeamModel
    {
        return $this->team->firstWhere('role', OperationRole::Surgeon);
    }

    /**
     * The booked slot, used to detect theatre clashes before the operation
     * has actually started.
     */
    public function scheduledEndsAt(): \Illuminate\Support\Carbon
    {
        return $this->scheduled_at->copy()->addMinutes($this->estimated_duration_minutes);
    }

    public function actualDurationMinutes(): ?int
    {
        if ($this->started_at === null || $this->ended_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            OperationStatus::Completed->value,
            OperationStatus::Cancelled->value,
        ]);
    }

    /**
     * Operations that still hold a theatre slot, which is what a clash check
     * has to look at.
     */
    public function scopeHoldingSlot(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OperationStatus::Scheduled->value,
            OperationStatus::Confirmed->value,
            OperationStatus::InProgress->value,
        ]);
    }
}
