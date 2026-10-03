<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Billing\Infrastructure\Persistence\Models\ServiceModel;
use Illuminate\Validation\Rule;

final class StoreServiceRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::BILLING_SERVICES_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = $this->route('service');
        $serviceId = $service instanceof ServiceModel ? $service->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('services', 'code')->ignore($serviceId)],
            'category' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
