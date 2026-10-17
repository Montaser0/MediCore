<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class DiagnosisCodeModel extends BaseModel
{
    protected $table = 'diagnosis_codes';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<ClinicModel, $this>
     */
    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(
            ClinicModel::class,
            'clinic_diagnosis_code',
            'diagnosis_code_id',
            'clinic_id',
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<DiagnosisCodeModel>  $query
     * @return Builder<DiagnosisCodeModel>
     */
    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->whereHas(
            'clinics',
            fn (Builder $clinics) => $clinics->where('clinics.id', $clinicId),
        );
    }

    /**
     * Diagnoses linked to the clinic assigned to this doctor.
     *
     * @param  Builder<DiagnosisCodeModel>  $query
     * @return Builder<DiagnosisCodeModel>
     */
    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        $doctor = DoctorModel::query()->with('clinics:clinics.id')->find($doctorId);
        $clinicIds = $doctor?->diagnosisClinicIds() ?? [];

        if ($clinicIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
            'clinics',
            fn (Builder $clinics) => $clinics->whereIn('clinics.id', $clinicIds),
        );
    }

    public static function nextLocalCode(): string
    {
        $latest = static::query()
            ->where('system', 'local')
            ->orderByDesc('id')
            ->value('code');
        $number = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $number = ((int) $matches[1]) + 1;
        }

        return 'DX-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('code', 'like', $term.'%')
                ->orWhere('title', 'like', '%'.$term.'%');
        });
    }
}
