<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence\Factories;

use App\Core\Persistence\Factories\ArabicFaker;
use App\Modules\Patients\Domain\Enums\ClinicalRecordStatus;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientConditionModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientConditionModel>
 */
final class PatientConditionFactory extends Factory
{
    protected $model = PatientConditionModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => PatientModel::factory(),
            'condition_name' => fake()->randomElement(ArabicFaker::chronicConditions()),
            'diagnosed_at' => fake()->dateTimeBetween('-10 years', '-1 month')->format('Y-m-d'),
            'status' => ClinicalRecordStatus::Active->value,
        ];
    }
}
