<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence\Factories;

use App\Core\Persistence\Factories\ArabicFaker;
use App\Modules\Patients\Domain\Enums\AllergySeverity;
use App\Modules\Patients\Domain\Enums\ClinicalRecordStatus;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientAllergyModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientAllergyModel>
 */
final class PatientAllergyFactory extends Factory
{
    protected $model = PatientAllergyModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => PatientModel::factory(),
            'allergen' => fake()->randomElement(ArabicFaker::allergens()),
            'reaction' => fake()->randomElement(['طفح جلدي', 'حكة شديدة', 'وذمة وعائية', 'ضيق تنفس', 'إقياء']),
            'severity' => fake()->randomElement([
                AllergySeverity::Mild->value,
                AllergySeverity::Moderate->value,
                AllergySeverity::Severe->value,
            ]),
            'status' => ClinicalRecordStatus::Active->value,
        ];
    }

    public function severe(): self
    {
        return $this->state(fn (): array => ['severity' => AllergySeverity::Severe->value]);
    }
}
