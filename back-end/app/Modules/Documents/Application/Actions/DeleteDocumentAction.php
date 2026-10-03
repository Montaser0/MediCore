<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application\Actions;

use App\Core\Storage\FileStorage;
use App\Core\Support\Contracts\AuditRecorder;
use App\Modules\Documents\Infrastructure\Persistence\Models\FileModel;
use Illuminate\Support\Facades\DB;

final class DeleteDocumentAction
{
    public function __construct(
        private readonly FileStorage $storage,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Medical records are never hard-deleted by default: the row is soft
     * deleted so the audit trail still resolves, and the bytes are only
     * removed when explicitly purged.
     */
    public function execute(FileModel $file, bool $purgeBytes = false): void
    {
        DB::transaction(function () use ($file, $purgeBytes): void {
            $this->audit->record(
                action: 'documents.deleted',
                entityType: FileModel::class,
                entityId: $file->getKey(),
                oldValues: [
                    'original_name' => $file->original_name,
                    'path' => $file->path,
                ],
            );

            $file->links()->delete();
            $file->delete();

            if ($purgeBytes) {
                $this->storage->delete($file->disk, $file->path);
            }
        });
    }
}
