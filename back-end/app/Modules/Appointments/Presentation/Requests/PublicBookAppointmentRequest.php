<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Presentation\Requests;

use App\Modules\Patients\Domain\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublicBookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['first_name', 'father_name', 'last_name', 'phone', 'national_id', 'reason'] as $field) {
            if ($this->exists($field)) {
                $trimmed[$field] = trim((string) $this->input($field));
            }
        }

        if ($trimmed !== []) {
            $this->merge($trimmed);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'father_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::in(Gender::values())],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'phone' => ['required', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'clinic_id' => ['required', 'integer', Rule::exists('clinics', 'id')->where('is_active', true)],
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'scheduled_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'الاسم مطلوب.',
            'father_name.required' => 'اسم الأب مطلوب.',
            'last_name.required' => 'الكنية مطلوبة.',
            'gender.required' => 'الجنس مطلوب.',
            'gender.in' => 'الجنس غير صالح.',
            'date_of_birth.required' => 'تاريخ الميلاد مطلوب.',
            'date_of_birth.before_or_equal' => 'تاريخ الميلاد لا يمكن أن يكون في المستقبل.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'clinic_id.required' => 'اختر العيادة.',
            'clinic_id.exists' => 'العيادة المختارة غير متاحة.',
            'doctor_id.required' => 'اختر الطبيب.',
            'doctor_id.exists' => 'الطبيب المختار غير متاح.',
            'scheduled_at.required' => 'موعد الزيارة مطلوب.',
            'reason.max' => 'سبب الزيارة طويل جداً.',
        ];
    }
}
