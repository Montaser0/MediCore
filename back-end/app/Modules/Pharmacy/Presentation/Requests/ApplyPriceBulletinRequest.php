<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class ApplyPriceBulletinRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_MEDICATIONS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'update_shelf' => ['sometimes', 'boolean'],
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.barcode' => ['nullable', 'string', 'max:100'],
            'rows.*.source_record_id' => ['nullable', 'string', 'max:40'],
            'rows.*.name' => ['nullable', 'string', 'max:180'],
            'rows.*.company' => ['nullable', 'string', 'max:80'],
            'rows.*.official_price' => ['required', 'numeric', 'min:0'],
            'rows.*.composition' => ['nullable', 'string', 'max:2000'],
            'rows.*.generic_name' => ['nullable', 'string', 'max:500'],
        ];
    }
}
