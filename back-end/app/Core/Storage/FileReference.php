<?php

declare(strict_types=1);

namespace App\Core\Storage;

/**
 * What other modules get back after storing a document. Deliberately carries
 * the API path rather than a public URL: files are always served through an
 * authorised endpoint, never from a guessable storage URL.
 */
final readonly class FileReference
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $originalName,
        public ?string $mimeType,
        public int $size,
        public string $downloadPath,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'download_path' => $this->downloadPath,
        ];
    }
}
