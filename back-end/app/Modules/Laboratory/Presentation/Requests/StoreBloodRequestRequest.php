<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Laboratory\Domain\BloodGroups;
use Illuminate\Validation\Rule;

final class StoreBloodRequestRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::ORDERS_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'blood_product_id' => ['required', 'integer', 'exists:blood_products,id'],
            'blood_group' => ['required', Rule::in(BloodGroups::values())],
            'units' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'blood_product_id.required' => 'اختر منتج الدم.',
            'blood_group.required' => 'اختر الزمرة.',
            'units.required' => 'أدخل عدد الوحدات.',
            'units.min' => 'عدد الوحدات يجب أن يكون 1 على الأقل.',
        ];
    }
}
