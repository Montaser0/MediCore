<?php

declare(strict_types=1);

namespace App\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Every module request extends this so permission checks live next to the
 * validation rules instead of being scattered through controllers.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * Permission required to send this request, or null when the route only
     * needs an authenticated user.
     */
    protected ?string $permission = null;

    public function authorize(): bool
    {
        if ($this->permission === null) {
            return $this->user() !== null;
        }

        return $this->user()?->can($this->permission) === true;
    }

    /**
     * Convenience accessor used by actions to know who triggered them.
     */
    public function actorId(): ?int
    {
        return $this->user()?->getKey();
    }
}
