<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Resolves the acting user and the hospital/department context that most
 * actions need, so modules do not reach for the auth facade directly.
 */
final class CurrentUser
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function model(): ?UserModel
    {
        $user = $this->auth->guard('sanctum')->user() ?? $this->auth->guard()->user();

        return $user instanceof UserModel ? $user : null;
    }

    public function id(): ?int
    {
        return $this->model()?->getKey();
    }

    public function hospitalId(): ?int
    {
        return $this->model()?->staff?->hospital_id;
    }

    public function departmentId(): ?int
    {
        return $this->model()?->staff?->department_id;
    }

    public function doctorId(): ?int
    {
        return $this->model()?->staff?->doctor?->getKey();
    }

    /**
     * A doctor account, and only that account, is limited to its own patients
     * and appointments. Administrators and every other role keep the full list.
     */
    public function ownDoctorId(): ?int
    {
        $user = $this->model();

        if ($user === null || $user->hasRole('admin')) {
            return null;
        }

        $roles = $user->getRoleNames();

        if ($roles->count() !== 1 || $roles->first() !== 'doctor') {
            return null;
        }

        return $this->doctorId() ?? 0;
    }

    public function isAuthenticated(): bool
    {
        return $this->model() !== null;
    }
}
