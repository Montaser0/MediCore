<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Services;

/**
 * The few DICOM tags the file needs: who the picture belongs to, and which
 * study it is. Pixel data is never read.
 */
final readonly class DicomIdentity
{
    public function __construct(
        public ?string $patientName,
        public ?string $patientId,
        public ?string $studyInstanceUid,
        public ?string $accessionNumber,
        public ?string $modality,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null, null);
    }
}
