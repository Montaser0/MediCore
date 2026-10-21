<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class AssetNoteRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::ASSETS_MANAGE;

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['department_id', 'location', 'note'] as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $merged[$key] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
