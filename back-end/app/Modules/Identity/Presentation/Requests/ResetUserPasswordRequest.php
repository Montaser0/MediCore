<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetUserPasswordRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::USERS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ];
    }
}
