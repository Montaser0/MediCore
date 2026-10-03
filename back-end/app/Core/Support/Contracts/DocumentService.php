<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

use App\Core\Storage\FileReference;
use Illuminate\Http\UploadedFile;

/**
 * The single upload path in the system. Radiology images, lab attachments,
 * consent forms and scanned IDs all go through here, so access control and
 * storage layout are defined once.
 */
interface DocumentService
{
    public function attach(
        UploadedFile $file,
        string $entityType,
        int $entityId,
        ?string $category = null,
        ?int $patientId = null,
        ?int $encounterId = null,
    ): FileReference;

    /**
     * @return array<int, FileReference>
     */
    public function listFor(string $entityType, int $entityId, ?string $category = null): array;

    public function countFor(string $entityType, int $entityId, ?string $category = null): int;
}
