<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Services;

use Illuminate\Http\UploadedFile;

/**
 * Isolates everything DICOM-specific so a PACS can be plugged in later
 * without touching the actions. For now it recognises DICOM files and reads
 * the identifiers we care about; a real PACS integration replaces the body of
 * these methods, not their callers.
 */
final class DicomService
{
    private const DICOM_MAGIC = 'DICM';

    private const DICOM_MAGIC_OFFSET = 128;

    public function isDicom(UploadedFile $file): bool
    {
        if (in_array(strtolower((string) $file->getClientOriginalExtension()), ['dcm', 'dicom'], true)) {
            return true;
        }

        $handle = @fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            fseek($handle, self::DICOM_MAGIC_OFFSET);

            return fread($handle, 4) === self::DICOM_MAGIC;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Study-level identifiers, when the uploaded file carries them.
     *
     * @return array{study_instance_uid: ?string, accession_number: ?string}
     */
    public function readStudyIdentifiers(UploadedFile $file): array
    {
        // Reading the tag dictionary needs a DICOM library; until a PACS is
        // wired in, the technician supplies these on the study instead.
        return [
            'study_instance_uid' => null,
            'accession_number' => null,
        ];
    }
}
