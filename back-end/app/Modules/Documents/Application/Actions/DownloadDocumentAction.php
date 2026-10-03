<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application\Actions;

use App\Core\Storage\FileStorage;
use App\Core\Support\Contracts\AuditRecorder;
use App\Modules\Documents\Infrastructure\Persistence\Models\FileModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files are never exposed as a public storage URL; every read goes through
 * this action so it can be authorised and audited.
 */
final class DownloadDocumentAction
{
    public function __construct(
        private readonly FileStorage $storage,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(FileModel $file, bool $inline = false): StreamedResponse
    {
        $this->audit->record(
            action: $inline ? 'documents.viewed' : 'documents.downloaded',
            entityType: FileModel::class,
            entityId: $file->getKey(),
        );

        return $inline
            ? $this->storage->stream($file->disk, $file->path)
            : $this->storage->download($file->disk, $file->path, $file->original_name);
    }
}
