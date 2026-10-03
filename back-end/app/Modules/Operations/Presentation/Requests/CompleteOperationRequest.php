<?php

declare(strict_types=1);

namespace App\Modules\Operations\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class CompleteOperationRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::OPERATIONS_UPDATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operative_note' => ['required', 'string', 'min:20'],
            'post_op_diagnosis' => ['required', 'string', 'max:2000'],
            'post_op_instructions' => ['nullable', 'string'],
            'complications' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string', 'max:50'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
            'ended_at' => ['nullable', 'date'],
        ];
    }
}
