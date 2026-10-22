<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Services;

use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Turns whatever the radiology desk sends — loose files, a study folder, or a
 * ZIP — into the medical objects worth keeping. DICOMDIR and desk clutter
 * are dropped. The original DICOM bytes are not converted.
 */
final class ImagingIntake
{
    /** @var array<int, string> */
    private const MEDICAL_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'dcm', 'dicom'];

    public function __construct(private readonly DicomService $dicom) {}

    /**
     * @param  array<int, UploadedFile>  $uploads
     */
    public function collect(array $uploads): ImagingPackage
    {
        $workspace = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hms-imaging-'.bin2hex(random_bytes(8));
        mkdir($workspace, 0700, true);

        $accepted = [];
        $skipped = 0;

        try {
            foreach ($uploads as $upload) {
                $this->take($upload, $workspace, $accepted, $skipped, 0);
            }

            if (count($accepted) > $this->limit('max_study_files', 2000)) {
                throw BusinessRuleException::make('عدد ملفات الفحص أكبر من الحد المسموح (2000 ملفاً).');
            }
        } catch (\Throwable $error) {
            (new ImagingPackage([], $skipped, $workspace))->cleanup();
            throw $error;
        }

        if ($accepted === []) {
            (new ImagingPackage([], $skipped, $workspace))->cleanup();

            throw BusinessRuleException::make('لا توجد صور طبية في الملفات المحددة. المسموح DICOM وJPG وPNG وPDF، أو ملف ZIP لمجلد الفحص.');
        }

        return new ImagingPackage($accepted, $skipped, $workspace);
    }

    /**
     * @param  array<int, AcceptedImage>  $accepted
     */
    private function take(UploadedFile $file, string $workspace, array &$accepted, int &$skipped, int $depth): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw BusinessRuleException::make('تعذر استلام الملف. إذا كان الفحص كبيراً ارفعه كملف ZIP لا يتجاوز حد الخادم.');
        }

        $name = $this->baseName($file->getClientOriginalName());

        if ($name === '' || $this->ignored($name)) {
            $skipped++;

            return;
        }

        if ($this->isZip($file) && $depth < 2) {
            $this->unpack($file, $workspace, $accepted, $skipped, $depth + 1);

            return;
        }

        $path = $file->getRealPath() ?: '';

        if (! $this->isMedical($path, $name)) {
            $skipped++;

            return;
        }

        if (count($accepted) >= $this->limit('max_study_files', 2000)) {
            throw BusinessRuleException::make('عدد ملفات الفحص أكبر من الحد المسموح (2000 ملفاً).');
        }

        $identity = $this->dicom->isDicomPath($path, $name) ? $this->dicom->readIdentity($path) : null;
        $mime = $identity !== null || $this->dicom->isDicomPath($path, $name)
            ? 'application/dicom'
            : $this->mimeFor($name);

        $accepted[] = new AcceptedImage($this->withMime($file, $name, $mime), $identity);
    }

    /**
     * @param  array<int, AcceptedImage>  $accepted
     */
    private function unpack(UploadedFile $file, string $workspace, array &$accepted, int &$skipped, int $depth): void
    {
        $zip = new ZipArchive();

        if ($zip->open($file->getRealPath() ?: '') !== true) {
            throw BusinessRuleException::make('تعذر فتح ملف الضغط.');
        }

        $extracted = 0;
        $ceiling = $this->limit('max_extracted_bytes', 1073741824);

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);

                if ($stat === false) {
                    $skipped++;
                    continue;
                }

                $entry = str_replace('\\', '/', (string) $stat['name']);

                if ($entry === '' || str_ends_with($entry, '/')) {
                    continue;
                }

                if ($this->unsafe($entry) || str_contains($entry, '__MACOSX/')) {
                    $skipped++;
                    continue;
                }

                $base = $this->baseName($entry);

                if ($base === '' || $this->ignored($base)) {
                    $skipped++;
                    continue;
                }

                $size = (int) ($stat['size'] ?? 0);

                if ($size <= 0) {
                    $skipped++;
                    continue;
                }

                $extracted += $size;

                if ($extracted > $ceiling) {
                    throw BusinessRuleException::make('حزمة الأشعة أكبر من الحد المسموح.');
                }

                $dest = $workspace.DIRECTORY_SEPARATOR.bin2hex(random_bytes(8)).'-'.$this->safeFile($base);
                $stream = $zip->getStream((string) $stat['name']);

                if ($stream === false) {
                    $skipped++;
                    continue;
                }

                $output = fopen($dest, 'wb');

                if ($output === false) {
                    fclose($stream);
                    throw BusinessRuleException::make('تعذر حفظ ملفات الحزمة.');
                }

                stream_copy_to_stream($stream, $output);
                fclose($output);
                fclose($stream);

                $this->take(
                    new UploadedFile($dest, $base, null, UPLOAD_ERR_OK, true),
                    $workspace,
                    $accepted,
                    $skipped,
                    $depth,
                );
            }
        } finally {
            $zip->close();
        }
    }

    private function isMedical(string $path, string $name): bool
    {
        if (in_array($this->extension($name), self::MEDICAL_EXTENSIONS, true)) {
            return true;
        }

        return $this->dicom->isDicomPath($path, $name);
    }

    private function isZip(UploadedFile $file): bool
    {
        $extension = $this->extension($file->getClientOriginalName());
        $mime = strtolower((string) $file->getClientMimeType());

        return $extension === 'zip' || in_array($mime, ['application/zip', 'application/x-zip-compressed'], true);
    }

    private function ignored(string $name): bool
    {
        $lower = strtolower(str_replace('\\', '/', $name));
        $parts = preg_split('/\/|__/', $lower) ?: [];
        $leaf = (string) (end($parts) ?: $lower);

        return in_array($leaf, ['dicomdir', '.ds_store', 'thumbs.db', 'desktop.ini'], true)
            || str_starts_with($leaf, '.');
    }

    private function unsafe(string $entry): bool
    {
        if (str_contains($entry, "\0") || str_starts_with($entry, '/') || preg_match('/^[A-Za-z]:/', $entry) === 1) {
            return true;
        }

        foreach (explode('/', $entry) as $part) {
            if ($part === '..') {
                return true;
            }
        }

        return false;
    }

    private function withMime(UploadedFile $file, string $name, string $mime): UploadedFile
    {
        if ($file->getClientMimeType() === $mime && $file->getClientOriginalName() === $name) {
            return $file;
        }

        return new UploadedFile($file->getRealPath() ?: '', $name, $mime, UPLOAD_ERR_OK, true);
    }

    private function mimeFor(string $name): string
    {
        return match ($this->extension($name)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function baseName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $base = basename($name);

        return trim($base);
    }

    private function safeFile(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $name) ?? 'image';

        return $name === '' ? 'image' : $name;
    }

    private function extension(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    private function limit(string $key, int $default): int
    {
        try {
            if (function_exists('config')) {
                $value = config('hms.documents.imaging.'.$key, $default);

                if (is_numeric($value)) {
                    return (int) $value;
                }
            }
        } catch (\Throwable) {
            // A unit test can construct the intake before the container boots.
        }

        return $default;
    }
}
