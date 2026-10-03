<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\AuditRecorder;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ResetPasswordAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * When $currentPassword is supplied the user is changing their own
     * password; otherwise an administrator is resetting it.
     */
    public function execute(UserModel $user, string $newPassword, ?string $currentPassword = null): void
    {
        if ($currentPassword !== null && ! Hash::check($currentPassword, $user->password)) {
            throw BusinessRuleException::make('كلمة المرور الحالية غير صحيحة.');
        }

        DB::transaction(function () use ($user, $newPassword, $currentPassword): void {
            $user->forceFill(['password' => $newPassword])->save();

            // Force every other device to log in again with the new password.
            $user->tokens()->delete();

            $this->audit->record(
                action: $currentPassword !== null ? 'auth.password_changed' : 'users.password_reset',
                entityType: UserModel::class,
                entityId: $user->getKey(),
            );
        });
    }
}
