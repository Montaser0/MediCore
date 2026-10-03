<?php

declare(strict_types=1);

namespace App\Core\Storage;

use App\Core\Exceptions\ResourceNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StorageManager implements FileStorage
{
    public function store(UploadedFile $file, string $directory): StoredFile
    {
        $disk = (string) config('hms.documents.disk', 'local');

        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $checksum = hash_file('sha256', $file->getRealPath()) ?: null;

        $path = Storage::disk($disk)->putFileAs($directory, $file, $storedName);

        return new StoredFile(
            disk: $disk,
            path: (string) $path,
            storedName: $storedName,
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getClientMimeType(),
            extension: $extension !== '' ? $extension : null,
            size: (int) $file->getSize(),
            checksum: $checksum,
        );
    }

    public function delete(string $disk, string $path): bool
    {
        return Storage::disk($disk)->delete($path);
    }

    public function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    public function download(string $disk, string $path, string $downloadName): StreamedResponse
    {
        $this->guardExists($disk, $path);

        return Storage::disk($disk)->download($path, $downloadName);
    }

    public function stream(string $disk, string $path): StreamedResponse
    {
        $this->guardExists($disk, $path);

        return Storage::disk($disk)->response($path);
    }

    private function guardExists(string $disk, string $path): void
    {
        if (! $this->exists($disk, $path)) {
            throw ResourceNotFoundException::for('File', $path);
        }
    }
}
