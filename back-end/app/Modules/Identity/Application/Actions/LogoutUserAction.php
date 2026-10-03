<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Core\Support\Contracts\AuditRecorder;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;

final class LogoutUserAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(UserModel $user, bool $allDevices = false): void
    {
        if ($allDevices) {
            $user->tokens()->delete();
        } else {
            $token = $user->currentAccessToken();

            if ($token !== null && method_exists($token, 'delete')) {
                $token->delete();
            }
        }

        $this->audit->record(
            action: $allDevices ? 'auth.logout_all' : 'auth.logout',
            entityType: UserModel::class,
            entityId: $user->getKey(),
        );
    }
}
