<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use Illuminate\Validation\Rule;

final class StoreStaffRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::STAFF_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hospital_id' => ['required', 'integer', 'exists:hospitals,id'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id', 'required_if:role,doctor'],
            'clinic_id' => [
                'nullable',
                'integer',
                'exists:clinics,id',
                'required_if:role,data_entry',
                Rule::requiredIf(fn () => $this->doctorMustChooseClinic()),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'is_doctor' => ['sometimes', 'boolean'],
            'role' => ['nullable', 'string', Rule::in(array_keys(Permissions::staffRoles()))],
            'license_number' => ['nullable', 'string', 'max:100'],
            'specialty' => ['nullable', 'string', 'max:150'],
            'username' => ['nullable', 'string', 'max:50', 'required_with:role', 'required_if:is_doctor,true', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:6', 'required_with:role', 'required_if:is_doctor,true'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'screens' => ['sometimes', 'array'],
            'screens.*' => ['string', Rule::in(array_keys(Permissions::screens()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'نوع الحساب غير معروف.',
            'department_id.required_if' => 'اختر القسم الذي يعمل فيه الطبيب.',
            'clinic_id.required' => 'اختر العيادة التي يعمل فيها الطبيب.',
            'clinic_id.required_if' => 'اختر العيادة التي يُحوَّل إليها المرضى.',
            'username.required_with' => 'اسم المستخدم مطلوب عند إنشاء حساب.',
            'username.required_if' => 'اسم المستخدم مطلوب عند إنشاء حساب.',
            'password.required_with' => 'كلمة المرور مطلوبة عند إنشاء حساب.',
            'password.required_if' => 'كلمة المرور مطلوبة عند إنشاء حساب.',
            'screens.*.in' => 'إحدى الشاشات المختارة غير معروفة.',
        ];
    }

    private function doctorMustChooseClinic(): bool
    {
        if ($this->input('role') !== 'doctor' && ! $this->boolean('is_doctor')) {
            return false;
        }

        $departmentId = $this->input('department_id');
        if ($departmentId === null || $departmentId === '') {
            return false;
        }

        return ClinicModel::query()
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->count() > 1;
    }
}
