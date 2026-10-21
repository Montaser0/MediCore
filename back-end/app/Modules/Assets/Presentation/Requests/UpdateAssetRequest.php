<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Assets\Domain\Enums\AssetCategory;
use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetModel;
use Illuminate\Validation\Rule;

final class UpdateAssetRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::ASSETS_MANAGE;

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach ([
            'brand',
            'model_name',
            'department_id',
            'location',
            'received_by',
            'delivered_on',
            'notes',
        ] as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $merged[$key] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $asset = $this->route('asset');
        $assetId = $asset instanceof ItAssetModel ? $asset->getKey() : null;
        $hospitalId = $asset instanceof ItAssetModel ? (int) $asset->hospital_id : 0;

        return [
            'asset_code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('it_assets', 'asset_code')->where('hospital_id', $hospitalId)->ignore($assetId),
            ],
            'category' => ['sometimes', 'required', Rule::enum(AssetCategory::class)],
            'brand' => ['nullable', 'string', 'max:100'],
            'model_name' => ['nullable', 'string', 'max:100'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('hospital_id', $hospitalId),
            ],
            'location' => ['nullable', 'string', 'max:150'],
            'received_by' => ['nullable', 'string', 'max:150'],
            'delivered_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
