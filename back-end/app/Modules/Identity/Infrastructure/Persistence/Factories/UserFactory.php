<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Factories;

use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserModel>
 */
final class UserFactory extends Factory
{
    protected $model = UserModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // اسم المستخدم بالحروف اللاتينية لأنه يُكتب عند تسجيل الدخول، أما
        // الاسم المعروض فيأتي من سجل الموظف بالعربية.
        return [
            'staff_id' => StaffModel::factory(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password!2026',
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function withRole(string $role): self
    {
        return $this->afterCreating(fn (UserModel $user) => $user->assignRole($role));
    }

    public function unverified(): self
    {
        return $this->state(fn (): array => ['remember_token' => Str::random(10)]);
    }
}
