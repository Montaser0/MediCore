<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Storage\FileReference;
use App\Core\Storage\FileStorage;
use App\Core\Support\Contracts\AuditRecorder;
use App\Core\Support\Contracts\DocumentService;
use App\Modules\Documents\Infrastructure\Persistence\Models\FileLinkModel;
use App\Modules\Documents\Infrastructure\Persistence\Models\FileModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores the bytes on the configured disk and the metadata in MySQL, then
 * links the file to the entity that owns it.
 */
final class UploadDocumentAction implements DocumentService
{
    public function __construct(
        private readonly FileStorage $storage,
        private readonly CurrentUser $currentUser,
        private readonly AuditRecorder $audit,
    ) {}

    public function attach(
        UploadedFile $file,
        string $entityType,
        int $entityId,
        ?string $category = null,
        ?int $patientId = null,
        ?int $encounterId = null,
    ): FileReference {
        $stored = $this->storage->store($file, $this->directoryFor($patientId, $encounterId, $category));

        return DB::transaction(function () use ($stored, $entityType, $entityId, $category, $patientId, $encounterId): FileReference {
            $record = FileModel::query()->create([
                'uuid' => (string) Str::uuid(),
                'original_name' => $stored->originalName,
                'stored_name' => $stored->storedName,
                'disk' => $stored->disk,
                'path' => $stored->path,
                'mime_type' => $stored->mimeType,
                'extension' => $stored->extension,
                'size' => $stored->size,
                'checksum' => $stored->checksum,
                'uploaded_by' => $this->currentUser->id(),
            ]);

            FileLinkModel::query()->create([
                'file_id' => $record->getKey(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'category' => $category,
                'patient_id' => $patientId,
                'encounter_id' => $encounterId,
            ]);

            $this->audit->record(
                action: 'documents.uploaded',
                entityType: FileModel::class,
                entityId: $record->getKey(),
                newValues: [
                    'original_name' => $record->original_name,
                    'linked_to' => $entityType.'#'.$entityId,
                    'category' => $category,
                ],
            );

            return $this->reference($record);
        });
    }

    /**
     * @return array<int, FileReference>
     */
    public function listFor(string $entityType, int $entityId, ?string $category = null): array
    {
        return FileModel::query()
            ->whereHas('links', fn ($query) => $query
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->when($category !== null, fn ($inner) => $inner->where('category', $category)))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FileModel $file) => $this->reference($file))
            ->all();
    }

    public function countFor(string $entityType, int $entityId, ?string $category = null): int
    {
        return FileLinkModel::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->count();
    }

    /**
     * Mirrors the layout described in the PRD:
     * patients/000154/encounters/00981/radiology/chest-xray-01.jpg
     */
    private function directoryFor(?int $patientId, ?int $encounterId, ?string $category): string
    {
        $segments = ['patients', $patientId !== null ? (string) $patientId : 'unassigned'];

        if ($encounterId !== null) {
            $segments[] = 'encounters';
            $segments[] = (string) $encounterId;
        }

        $segments[] = $category ?? 'general';

        return implode('/', $segments);
    }

    private function reference(FileModel $file): FileReference
    {
        return new FileReference(
            id: $file->getKey(),
            uuid: $file->uuid,
            originalName: $file->original_name,
            mimeType: $file->mime_type,
            size: (int) $file->size,
            downloadPath: $file->downloadPath(),
        );
    }
}
