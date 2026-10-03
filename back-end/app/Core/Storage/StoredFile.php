<?php

declare(strict_types=1);

namespace App\Core\Storage;

/**
 * Describes a file after it has been written to a disk, before it is recorded
 * in the `files` table.
 */
final readonly class StoredFile
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $storedName,
        public string $originalName,
        public ?string $mimeType,
        public ?string $extension,
        public int $size,
        public ?string $checksum,
    ) {}
}
