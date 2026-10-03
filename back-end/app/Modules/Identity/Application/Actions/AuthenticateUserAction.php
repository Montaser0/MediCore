<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Core\Support\Contracts\AuditRecorder;
use App\Modules\Identity\Application\DTOs\CredentialsData;
use App\Modules\Identity\Domain\Events\UserLoggedIn;
use App\Modules\Identity\Domain\Exceptions\AccountDisabledException;
use App\Modules\Identity\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Support\Facades\Hash;

/**
 * Issues a Sanctum token for a valid username/password pair.
 */
final class AuthenticateUserAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @return array{user: UserModel, token: string}
     */
    public function execute(CredentialsData $credentials): array
    {
        $user = UserModel::query()
            ->where('username', $credentials->username)
            ->orWhere('email', $credentials->username)
            ->first();

        // Hash::check on a dummy value keeps the response time constant whether
        // or not the username exists.
        if ($user === null || ! Hash::check($credentials->password, $user->password)) {
            throw InvalidCredentialsException::make();
        }

        if (! $user->is_active) {
            throw AccountDisabledException::make();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($credentials->deviceName)->plainTextToken;

        UserLoggedIn::dispatch($user, $credentials->ipAddress);

        $this->audit->record(
            action: 'auth.login',
            entityType: UserModel::class,
            entityId: $user->getKey(),
        );

        return ['user' => $user, 'token' => $token];
    }
}
