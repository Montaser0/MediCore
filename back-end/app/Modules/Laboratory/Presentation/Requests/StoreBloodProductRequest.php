<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodProductModel;
use Illuminate\Validation\Rule;

final class StoreBloodProductRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::LABORATORY_TESTS_MANAGE;

    protected function prepareForValidation(): void
    {
        BloodProductModel::ensureCatalog();

        if (trim((string) $this->input('code', '')) === '') {
            $this->merge(['code' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('blood_product');
        $productId = $product instanceof BloodProductModel ? $product->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('blood_products', 'code')->ignore($productId)],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'أدخل اسم منتج الدم.',
            'price.required' => 'أدخل السعر.',
        ];
    }
}
