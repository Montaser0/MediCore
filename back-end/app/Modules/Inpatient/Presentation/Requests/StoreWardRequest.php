<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\WardModel;
use Illuminate\Validation\Rule;

final class StoreWardRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::INPATIENT_BEDS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ward = $this->route('ward');
        $wardId = $ward instanceof WardModel ? $ward->getKey() : null;

        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', Rule::unique('wards', 'code')->ignore($wardId)],
            'type' => ['nullable', 'string', 'max:50'],
            'floor' => ['nullable', 'integer', 'min:0'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
