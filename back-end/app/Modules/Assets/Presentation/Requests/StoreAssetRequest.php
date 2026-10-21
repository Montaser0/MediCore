<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Assets\Domain\Enums\AssetCategory;
use Illuminate\Validation\Rule;

final class StoreAssetRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::ASSETS_MANAGE;

    protected function prepareForValidation(): void
    {
        $this->merge($this->blankToNull([
            'asset_code',
            'brand',
            'model_name',
            'department_id',
            'location',
            'received_by',
            'delivered_on',
            'notes',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hospitalId = $this->integer('hospital_id');

        return [
            'hospital_id' => ['required', 'integer', 'exists:hospitals,id'],
            'asset_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('it_assets', 'asset_code')->where('hospital_id', $hospitalId),
            ],
            'category' => ['required', Rule::enum(AssetCategory::class)],
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

    /**
     * @param  array<int, string>  $keys
     * @return array<string, null>
     */
    private function blankToNull(array $keys): array
    {
        $merged = [];

        foreach ($keys as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $merged[$key] = null;
            }
        }

        return $merged;
    }
}
