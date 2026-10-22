<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Services;

use Illuminate\Http\UploadedFile;

final readonly class AcceptedImage
{
    public function __construct(
        public UploadedFile $file,
        public ?DicomIdentity $dicom,
    ) {}
}
