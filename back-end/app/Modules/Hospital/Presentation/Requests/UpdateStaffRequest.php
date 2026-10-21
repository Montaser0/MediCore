<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Validation\Rule;

final class UpdateStaffRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::STAFF_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'clinic_id' => [
                'nullable',
                'integer',
                'exists:clinics,id',
                Rule::requiredIf(fn () => $this->doctorMustChooseClinic()),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'is_doctor' => ['sometimes', 'boolean'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'specialty' => ['nullable', 'string', 'max:150'],
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
            'clinic_id.required' => 'اختر العيادة التي يعمل فيها الطبيب.',
        ];
    }

    private function doctorMustChooseClinic(): bool
    {
        if (! $this->exists('clinic_id') && ! $this->exists('department_id')) {
            return false;
        }

        $staff = $this->route('staff');
        if (! $staff instanceof StaffModel) {
            return false;
        }

        if ($staff->user()->first()?->getRoleNames()->first() !== 'doctor') {
            return false;
        }

        $departmentId = $this->exists('department_id') ? $this->input('department_id') : $staff->department_id;
        if ($departmentId === null || $departmentId === '') {
            return false;
        }

        return ClinicModel::query()
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->count() > 1
            && ($this->input('clinic_id') === null || $this->input('clinic_id') === '');
    }
}
