<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Numbering\DailyNumber;
use App\Core\Support\PhoneNumber;
use App\Core\Support\RecordLookup;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Patients\Domain\Enums\Gender;
use App\Modules\Patients\Domain\Enums\PatientStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $mrn
 * @property Gender $gender
 * @property PatientStatus $status
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 */
final class PatientModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;
    use SoftDeletes;

    protected $table = 'patients';

    protected $casts = [
        'gender' => Gender::class,
        'status' => PatientStatus::class,
        'date_of_birth' => 'date',
    ];

    protected function mrn(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => DailyNumber::present($value),
            set: fn (?string $value) => [
                'mrn' => $value,
                'mrn_serial' => DailyNumber::serial($value),
            ],
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => [
                'phone' => $value,
                'phone_normalized' => PhoneNumber::normalize($value),
                'phone_tail' => PhoneNumber::tail($value),
            ],
        );
    }

    protected function phoneSecondary(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => [
                'phone_secondary' => $value,
                'phone_secondary_normalized' => PhoneNumber::normalize($value),
                'phone_secondary_tail' => PhoneNumber::tail($value),
            ],
        );
    }

    protected $appends = ['full_name', 'age'];

    /**
     * Arabic naming convention: given name, father, grandfather, family name.
     */
    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->father_name,
            $this->grandfather_name,
            $this->last_name,
        ])));
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth === null
            ? null
            : (int) $this->date_of_birth->diffInYears(now());
    }

    /**
     * @return HasMany<EncounterModel, $this>
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(EncounterModel::class, 'patient_id');
    }

    /**
     * @return HasOne<EncounterModel, $this>
     */
    public function latestEncounter(): HasOne
    {
        return $this->hasOne(EncounterModel::class, 'patient_id')->latestOfMany();
    }

    /**
     * @return HasMany<AppointmentModel, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(AppointmentModel::class, 'patient_id');
    }

    /**
     * @return HasMany<PatientContactModel, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(PatientContactModel::class, 'patient_id');
    }

    /**
     * @return HasMany<PatientAllergyModel, $this>
     */
    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergyModel::class, 'patient_id');
    }

    /**
     * @return HasMany<PatientConditionModel, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(PatientConditionModel::class, 'patient_id');
    }

    /**
     * Matches the reception search box: MRN, name, phone, national id or date
     * of birth.
     *
     * @param  Builder<PatientModel>  $query
     * @return Builder<PatientModel>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        return RecordLookup::patients($query, $term);
    }
}
