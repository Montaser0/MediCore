<?php

declare(strict_types=1);

namespace App\Modules\Departments\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Departments\Domain\Enums\DepartmentType;
use Illuminate\Validation\Rule;

final class StoreDepartmentRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::DEPARTMENTS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hospital_id' => ['required', 'integer', 'exists:hospitals,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->where('hospital_id', $this->integer('hospital_id')),
            ],
            'type' => ['required', Rule::enum(DepartmentType::class)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
