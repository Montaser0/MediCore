<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyServiceModel;
use Illuminate\Validation\Rule;

final class StoreRadiologyServiceRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::RADIOLOGY_SERVICES_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = $this->route('radiology_service');
        $serviceId = $service instanceof RadiologyServiceModel ? $service->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('radiology_services', 'code')->ignore($serviceId)],
            'modality' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'preparation_instructions' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'requires_contrast' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
