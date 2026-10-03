<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Infrastructure\Persistence\Factories;

use App\Core\Persistence\Factories\ArabicFaker;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorModel>
 */
final class DoctorFactory extends Factory
{
    protected $model = DoctorModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => StaffModel::factory()->doctor(),
            'license_number' => 'LIC-'.fake()->unique()->numerify('#####'),
            'specialty' => ArabicFaker::specialty(),
        ];
    }
}
