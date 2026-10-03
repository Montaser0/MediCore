<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence\Factories;

use App\Core\Persistence\Factories\ArabicFaker;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientContactModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientContactModel>
 */
final class PatientContactFactory extends Factory
{
    protected $model = PatientContactModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => PatientModel::factory(),
            'name' => ArabicFaker::firstName().' '.ArabicFaker::familyName(),
            'relationship' => fake()->randomElement(ArabicFaker::relationships()),
            'phone' => ArabicFaker::mobile(),
            'is_emergency_contact' => true,
        ];
    }
}
