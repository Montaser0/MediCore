<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class ReceiveMedicationStockRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_MEDICATIONS_MANAGE;

    protected function prepareForValidation(): void
    {
        foreach (['barcode', 'unit', 'batch_number', 'notes'] as $field) {
            if ($this->exists($field) && trim((string) $this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'batch_number' => ['required', 'string', 'max:100'],
            'expiry_date' => ['required', 'date', 'after:today'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'اختر المستودع.',
            'quantity.required' => 'أدخل الكمية.',
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر.',
            'batch_number.required' => 'أدخل رقم الدفعة.',
            'expiry_date.required' => 'أدخل تاريخ الصلاحية.',
            'expiry_date.after' => 'لا يمكن استلام صنف منتهي الصلاحية.',
        ];
    }
}
