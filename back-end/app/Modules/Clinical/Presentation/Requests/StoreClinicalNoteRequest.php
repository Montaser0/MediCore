<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Clinical\Domain\Enums\NoteType;
use Illuminate\Validation\Rule;

final class StoreClinicalNoteRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::CLINICAL_NOTES_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note_type' => ['nullable', Rule::in(NoteType::values())],
            'content' => ['required', 'string', 'min:2', 'max:20000'],
        ];
    }
}
