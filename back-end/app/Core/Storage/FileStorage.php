<?php

declare(strict_types=1);

namespace App\Core\Storage;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Abstraction over local disks and S3-compatible object storage, so switching
 * a hospital from a local NAS to R2 is a configuration change only.
 */
interface FileStorage
{
    public function store(UploadedFile $file, string $directory): StoredFile;

    public function delete(string $disk, string $path): bool;

    public function exists(string $disk, string $path): bool;

    public function download(string $disk, string $path, string $downloadName): StreamedResponse;

    public function stream(string $disk, string $path): StreamedResponse;
}
