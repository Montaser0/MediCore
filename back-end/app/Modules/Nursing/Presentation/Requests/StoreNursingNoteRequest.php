<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Nursing\Domain\Enums\NursingNoteType;
use App\Modules\Nursing\Domain\Enums\NursingShift;
use Illuminate\Validation\Rule;

final class StoreNursingNoteRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::NURSING_NOTES_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note_type' => ['nullable', Rule::in(NursingNoteType::values())],
            'shift' => ['nullable', Rule::in(NursingShift::values())],
            'admission_id' => ['nullable', 'integer', 'exists:admissions,id'],
            'content' => ['required', 'string', 'min:3'],
        ];
    }
}
