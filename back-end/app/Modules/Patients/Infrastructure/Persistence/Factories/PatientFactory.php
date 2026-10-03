<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence\Factories;

use App\Core\Persistence\Factories\ArabicFaker;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Patients\Domain\Enums\Gender;
use App\Modules\Patients\Domain\Enums\PatientStatus;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientModel>
 */
final class PatientFactory extends Factory
{
    protected $model = PatientModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement([Gender::Male, Gender::Female]);

        // اسم الأب والجد يُبنيان من أسماء الذكور لأن ذلك ما تفعله الهوية.
        $father = ArabicFaker::maleName();

        return [
            'hospital_id' => HospitalModel::query()->value('id')
                ?? HospitalModel::factory(),
            'mrn' => 'MRN-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'first_name' => ArabicFaker::firstName($gender->value),
            'father_name' => $father,
            'grandfather_name' => ArabicFaker::maleName(),
            'last_name' => ArabicFaker::familyName(),
            'mother_name' => ArabicFaker::femaleName().' '.ArabicFaker::familyName(),
            'gender' => $gender->value,
            'date_of_birth' => fake()->dateTimeBetween('-85 years', '-1 year')->format('Y-m-d'),
            'national_id' => ArabicFaker::nationalId(),
            'phone' => ArabicFaker::mobile(),
            'address' => ArabicFaker::address(),
            'city' => ArabicFaker::city(),
            'nationality' => 'سوري',
            'blood_type' => fake()->randomElement(ArabicFaker::bloodTypes()),
            'marital_status' => fake()->randomElement(ArabicFaker::maritalStatuses()),
            'occupation' => ArabicFaker::occupation(),
            'status' => PatientStatus::Active->value,
        ];
    }

    public function child(): self
    {
        return $this->state(fn (): array => [
            'date_of_birth' => fake()->dateTimeBetween('-12 years', '-1 month')->format('Y-m-d'),
            'marital_status' => 'أعزب',
            'occupation' => 'طالب',
            'national_id' => null,
        ]);
    }

    public function female(): self
    {
        return $this->state(fn (): array => [
            'gender' => Gender::Female->value,
            'first_name' => ArabicFaker::femaleName(),
        ]);
    }

    public function male(): self
    {
        return $this->state(fn (): array => [
            'gender' => Gender::Male->value,
            'first_name' => ArabicFaker::maleName(),
        ]);
    }

    public function deceased(): self
    {
        return $this->state(fn (): array => ['status' => PatientStatus::Deceased->value]);
    }
}
