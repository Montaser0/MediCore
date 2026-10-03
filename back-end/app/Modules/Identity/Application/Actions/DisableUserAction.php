<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\AuditRecorder;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Support\Facades\DB;

final class DisableUserAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(UserModel $user, ?int $actingUserId = null): UserModel
    {
        if ($actingUserId !== null && $actingUserId === $user->getKey()) {
            throw BusinessRuleException::make('لا يمكنك تعطيل حسابك.');
        }

        return DB::transaction(function () use ($user): UserModel {
            $user->forceFill(['is_active' => false])->save();

            // Revoking tokens is what actually ends the session; the flag alone
            // would leave an issued bearer token working until it expired.
            $user->tokens()->delete();

            $this->audit->record(
                action: 'users.disabled',
                entityType: UserModel::class,
                entityId: $user->getKey(),
            );

            return $user;
        });
    }
}
