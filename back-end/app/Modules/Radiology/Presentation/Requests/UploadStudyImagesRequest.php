<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class UploadStudyImagesRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::RADIOLOGY_IMAGES_UPLOAD;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('hms.documents.max_upload_kb', 20480);

        return [
            'images' => ['required', 'array', 'min:1', 'max:50'],
            'images.*' => ['required', 'file', 'max:'.$maxKb],
        ];
    }
}
