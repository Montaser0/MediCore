<?php

declare(strict_types=1);

namespace App\Modules\Departments\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Departments\Domain\Enums\DepartmentType;
use Illuminate\Validation\Rule;

final class UpdateDepartmentRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::DEPARTMENTS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $department = $this->route('department');

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('departments', 'code')
                    ->where('hospital_id', $department?->hospital_id)
                    ->ignore($department?->getKey()),
            ],
            'type' => ['sometimes', Rule::enum(DepartmentType::class)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
