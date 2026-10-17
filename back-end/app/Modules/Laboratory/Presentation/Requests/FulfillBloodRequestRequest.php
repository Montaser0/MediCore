<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Laboratory\Domain\BloodGroups;
use Illuminate\Validation\Rule;

final class FulfillBloodRequestRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::LABORATORY_RESULTS_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmed_blood_group' => ['required', Rule::in(BloodGroups::values())],
            'bag_number' => ['required', 'string', 'max:80'],
            'issued_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed_blood_group.required' => 'أكّد الزمرة.',
            'bag_number.required' => 'أدخل رقم كيس الدم.',
            'issued_at.required' => 'أدخل التاريخ.',
        ];
    }
}
