<?php

declare(strict_types=1);

namespace App\Modules\Orders\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Orders\Domain\Enums\OrderPriority;
use App\Modules\Orders\Domain\Enums\OrderType;
use Illuminate\Validation\Rule;

final class StoreOrderRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::ORDERS_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'type' => ['required', Rule::in(OrderType::values())],
            'priority' => ['nullable', Rule::in(OrderPriority::values())],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'clinical_notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.service_type' => ['nullable', 'string', 'max:100'],
            'items.*.service_id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
