<?php

declare(strict_types=1);

namespace App\Modules\Operations\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationTypeModel;
use Illuminate\Validation\Rule;

final class StoreOperationTypeRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::DEPARTMENTS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->route('type');
        $typeId = $type instanceof OperationTypeModel ? $type->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('operation_types', 'code')->ignore($typeId)],
            'clinic_id' => ['required', 'integer', 'exists:clinics,id'],
            'specialty' => ['nullable', 'string', 'max:100'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'risk_level' => ['nullable', Rule::in(['low', 'moderate', 'high', 'critical'])],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
