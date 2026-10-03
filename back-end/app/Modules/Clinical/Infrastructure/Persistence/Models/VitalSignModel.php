<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Clinical\Domain\ValueObjects\VitalSignRange;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VitalSignModel extends BaseModel
{
    protected $table = 'vital_signs';

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'systolic_bp' => 'integer',
            'diastolic_bp' => 'integer',
            'heart_rate' => 'integer',
            'respiratory_rate' => 'integer',
            'oxygen_saturation' => 'float',
            'weight' => 'float',
            'height' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'recorded_by');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function abnormalities(): array
    {
        return VitalSignRange::abnormalities($this->only([
            'temperature', 'systolic_bp', 'diastolic_bp',
            'heart_rate', 'respiratory_rate', 'oxygen_saturation',
        ]));
    }

    public function bloodPressure(): ?string
    {
        if ($this->systolic_bp === null || $this->diastolic_bp === null) {
            return null;
        }

        return "{$this->systolic_bp}/{$this->diastolic_bp}";
    }

    /**
     * Body mass index, with height stored in centimetres.
     */
    public function bmi(): ?float
    {
        if (! $this->weight || ! $this->height) {
            return null;
        }

        $metres = $this->height / 100;

        return round($this->weight / ($metres ** 2), 1);
    }
}
