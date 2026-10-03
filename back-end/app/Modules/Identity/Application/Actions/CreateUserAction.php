<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Identity\Application\DTOs\UserData;
use App\Modules\Identity\Domain\Events\UserCreated;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Support\Facades\DB;

final class CreateUserAction
{
    public function execute(UserData $data): UserModel
    {
        if ($data->password === null) {
            throw BusinessRuleException::make('كلمة المرور مطلوبة عند إنشاء المستخدم.');
        }

        return DB::transaction(function () use ($data): UserModel {
            $user = UserModel::query()->create($data->attributes());

            if ($data->roles !== []) {
                $user->syncRoles($data->roles);
            }

            if ($data->departmentIds !== []) {
                $user->departments()->sync($data->departmentIds);
            }

            UserCreated::dispatch($user);

            return $user->fresh(['staff.department', 'roles', 'departments']);
        });
    }
}
