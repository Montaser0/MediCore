<?php

declare(strict_types=1);

namespace App\Modules\Documents\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Documents\Domain\Enums\DocumentCategory;
use Illuminate\Validation\Rule;

final class UploadDocumentRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::DOCUMENTS_UPLOAD;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('hms.documents.max_upload_kb'),
                'mimes:'.implode(',', config('hms.documents.allowed_mimes')),
            ],
            'entity_type' => ['required', 'string', 'max:100'],
            'entity_id' => ['required', 'integer', 'min:1'],
            'category' => ['nullable', Rule::in(DocumentCategory::values())],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
        ];
    }
}
