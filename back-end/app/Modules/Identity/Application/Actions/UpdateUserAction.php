<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Support\Facades\DB;

final class UpdateUserAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(UserModel $user, array $data): UserModel
    {
        return DB::transaction(function () use ($user, $data): UserModel {
            $user->fill(array_filter(
                [
                    'username' => $data['username'] ?? null,
                    'email' => $data['email'] ?? null,
                    'staff_id' => $data['staff_id'] ?? null,
                    'is_active' => $data['is_active'] ?? null,
                ],
                static fn (mixed $value) => $value !== null,
            ))->save();

            if (array_key_exists('roles', $data)) {
                $user->syncRoles($data['roles']);
            }

            if (array_key_exists('department_ids', $data)) {
                $user->departments()->sync($data['department_ids']);
            }

            return $user->fresh(['staff.department', 'roles', 'departments']);
        });
    }
}
