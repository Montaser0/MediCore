<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Validator;

final class UpdatePublicBookingSettingsRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::APPOINTMENTS_CREATE;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'opens_at' => $this->filled('opens_at') ? $this->input('opens_at') : null,
            'closes_at' => $this->filled('closes_at') ? $this->input('closes_at') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'open' => ['required', 'boolean'],
            'opens_at' => ['nullable', 'date_format:H:i', 'required_with:closes_at'],
            'closes_at' => ['nullable', 'date_format:H:i', 'required_with:opens_at'],
            'clinics' => ['present', 'array'],
            'clinics.*.clinic_id' => ['required', 'integer', 'distinct', 'exists:clinics,id'],
            'clinics.*.capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'clinics.*.doctor_ids' => ['required', 'array', 'min:1'],
            'clinics.*.doctor_ids.*' => ['integer', 'distinct', 'exists:doctors,id'],
            'clinics.*.times' => ['required', 'array', 'min:1'],
            'clinics.*.times.*' => ['date_format:H:i', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $opens = $this->input('opens_at');
            $closes = $this->input('closes_at');

            if (is_string($opens) && is_string($closes) && $opens !== '' && $closes !== '' && $closes <= $opens) {
                $validator->errors()->add('closes_at', 'وقت الإغلاق يجب أن يكون بعد وقت الفتح.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opens_at.required_with' => 'حدد وقت الفتح ووقت الإغلاق معاً، أو اتركهما فارغين.',
            'closes_at.required_with' => 'حدد وقت الفتح ووقت الإغلاق معاً، أو اتركهما فارغين.',
            'opens_at.date_format' => 'وقت الفتح غير صالح.',
            'closes_at.date_format' => 'وقت الإغلاق غير صالح.',
            'clinics.*.clinic_id.required' => 'اختر العيادة.',
            'clinics.*.clinic_id.exists' => 'العيادة المختارة غير موجودة.',
            'clinics.*.capacity.required' => 'حدد سعة العيادة.',
            'clinics.*.capacity.min' => 'سعة العيادة يجب أن تكون زيارة واحدة على الأقل.',
            'clinics.*.doctor_ids.required' => 'اختر أطباء العيادة.',
            'clinics.*.doctor_ids.min' => 'اختر طبيباً واحداً على الأقل لكل عيادة.',
            'clinics.*.doctor_ids.*.exists' => 'أحد الأطباء غير موجود.',
            'clinics.*.times.required' => 'حدد أوقات العيادة.',
            'clinics.*.times.min' => 'أضف وقتاً واحداً على الأقل لكل عيادة.',
            'clinics.*.times.*.date_format' => 'أحد الأوقات غير صالح.',
        ];
    }
}
