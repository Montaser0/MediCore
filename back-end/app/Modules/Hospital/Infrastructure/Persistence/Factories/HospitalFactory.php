<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Infrastructure\Persistence\Factories;

use App\Core\Persistence\Factories\ArabicFaker;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HospitalModel>
 */
final class HospitalFactory extends Factory
{
    protected $model = HospitalModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = ArabicFaker::city();

        return [
            'name' => 'مشفى '.$city.' الوطني',
            'code' => strtoupper(fake()->unique()->bothify('HOSP###')),
            'phone' => '+963 11 '.fake()->numerify('### ####'),
            'email' => fake()->unique()->safeEmail(),
            'address' => ArabicFaker::address(),
            'city' => $city,
            'country' => 'سوريا',
            'timezone' => 'Asia/Damascus',
            'currency' => 'USD',
            'is_active' => true,
        ];
    }
}
