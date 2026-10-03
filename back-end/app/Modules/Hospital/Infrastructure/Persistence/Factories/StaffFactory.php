<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Infrastructure\Persistence\Factories;

use App\Core\Persistence\Factories\ArabicFaker;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffModel>
 */
final class StaffFactory extends Factory
{
    protected $model = StaffModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hospital_id' => HospitalModel::query()->value('id') ?? HospitalModel::factory(),
            'employee_number' => 'EMP-'.str_pad((string) fake()->unique()->numberBetween(2, 9999), 4, '0', STR_PAD_LEFT),
            'first_name' => ArabicFaker::firstName(fake()->randomElement(['male', 'female'])),
            'last_name' => ArabicFaker::familyName(),
            'phone' => ArabicFaker::mobile(),
            'job_title' => ArabicFaker::jobTitle(),
            'is_active' => true,
        ];
    }

    public function doctor(): self
    {
        return $this->state(fn (): array => ['job_title' => 'طبيب اختصاصي']);
    }

    public function nurse(): self
    {
        return $this->state(fn (): array => ['job_title' => 'ممرض']);
    }

    public function forDepartment(int $departmentId): self
    {
        return $this->state(fn (): array => ['department_id' => $departmentId]);
    }
}
