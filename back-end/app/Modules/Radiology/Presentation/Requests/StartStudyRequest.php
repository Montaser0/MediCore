<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StartStudyRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::RADIOLOGY_STUDIES_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accession_number' => ['nullable', 'string', 'max:100'],
            'study_instance_uid' => ['nullable', 'string', 'max:255'],
            'technician_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
