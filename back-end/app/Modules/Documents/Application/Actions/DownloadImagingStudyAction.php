<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application\Actions;

use App\Core\Exceptions\ResourceNotFoundException;
use App\Core\Support\Contracts\AuditRecorder;
use App\Modules\Documents\Infrastructure\Persistence\Models\FileLinkModel;
use App\Modules\Documents\Infrastructure\Persistence\Models\FileModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * One download for a whole imaging study, so a CT of hundreds of DICOM
 * slices does not have to be fetched file by file from the patient record.
 */
final class DownloadImagingStudyAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(int $patientId, int $studyId): BinaryFileResponse
    {
        $links = FileLinkModel::query()
            ->with('file')
            ->where('patient_id', $patientId)
            ->where('entity_type', 'radiology_study')
            ->where('entity_id', $studyId)
            ->where('category', 'radiology_image')
            ->orderBy('id')
            ->get()
            ->filter(fn (FileLinkModel $link) => $link->file !== null)
            ->values();

        if ($links->isEmpty()) {
            throw ResourceNotFoundException::for('imaging study', $studyId);
        }

        $study = RadiologyStudyModel::query()->with('orderItem.service')->find($studyId);
        $label = trim((string) ($study?->orderItem?->service?->name ?: $study?->study_number ?: 'imaging'));
        $label = preg_replace('/[^\p{L}\p{N}\-_ ]/u', '', $label) ?: 'imaging';

        $temp = tempnam(sys_get_temp_dir(), 'hmsimg');

        if ($temp === false) {
            throw ResourceNotFoundException::for('imaging study', $studyId);
        }

        $zipPath = $temp.'.zip';
        rename($temp, $zipPath);

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw ResourceNotFoundException::for('imaging study', $studyId);
        }

        $copies = [];
        $used = [];

        try {
            foreach ($links as $index => $link) {
                $file = $link->file;

                if (! $file instanceof FileModel) {
                    continue;
                }

                $local = $this->localPath($file, $copies);

                if ($local === null) {
                    continue;
                }

                $name = $this->entryName($file->original_name, $index, $used);
                $zip->addFile($local, $name);
            }

            if ($zip->numFiles < 1) {
                throw ResourceNotFoundException::for('imaging study', $studyId);
            }
        } catch (\Throwable $error) {
            $zip->close();
            $this->discard($zipPath, $copies);
            throw $error;
        }

        $zip->close();
        $this->discard(null, $copies);

        $this->audit->record(
            action: 'documents.downloaded',
            entityType: RadiologyStudyModel::class,
            entityId: $studyId,
            newValues: ['patient_id' => $patientId, 'files' => $links->count()],
        );

        return response()->download($zipPath, $label.'.zip')->deleteFileAfterSend(true);
    }

    /**
     * @param  array<int, string>  $copies
     */
    private function localPath(FileModel $file, array &$copies): ?string
    {
        $disk = Storage::disk($file->disk);

        try {
            $path = $disk->path($file->path);

            if (is_file($path)) {
                return $path;
            }
        } catch (\Throwable) {
            // Object storage has no local path; copy the stream below.
        }

        $stream = $disk->readStream($file->path);

        if (! is_resource($stream)) {
            return null;
        }

        $copy = tempnam(sys_get_temp_dir(), 'hmsimg');

        if ($copy === false) {
            fclose($stream);

            return null;
        }

        $output = fopen($copy, 'wb');

        if ($output === false) {
            fclose($stream);
            unlink($copy);

            return null;
        }

        stream_copy_to_stream($stream, $output);
        fclose($stream);
        fclose($output);
        $copies[] = $copy;

        return $copy;
    }

    /**
     * @param  array<string, true>  $used
     */
    private function entryName(string $original, int $index, array &$used): string
    {
        $base = basename(str_replace('\\', '/', $original));
        $base = str_replace(["\0", '/', '\\'], '', $base);
        $base = $base !== '' ? $base : 'image-'.($index + 1);

        if (isset($used[$base])) {
            $base = ($index + 1).'-'.$base;
        }

        $used[$base] = true;

        return $base;
    }

    /**
     * @param  array<int, string>  $copies
     */
    private function discard(?string $zipPath, array $copies): void
    {
        foreach ($copies as $copy) {
            if (is_file($copy)) {
                unlink($copy);
            }
        }

        if ($zipPath !== null && is_file($zipPath)) {
            unlink($zipPath);
        }
    }
}
