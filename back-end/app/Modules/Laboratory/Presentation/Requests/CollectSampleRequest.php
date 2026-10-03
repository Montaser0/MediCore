<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class CollectSampleRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::LABORATORY_SAMPLES_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sample_type' => ['nullable', 'string', 'max:100'],
        ];
    }
}
