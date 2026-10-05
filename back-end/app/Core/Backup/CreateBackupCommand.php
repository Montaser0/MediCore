<?php

declare(strict_types=1);

namespace App\Core\Backup;

use Illuminate\Console\Command;

final class CreateBackupCommand extends Command
{
    protected $signature = 'hms:backup {target=local} {--drive=} {--label=pharmacy}';

    protected $description = 'Write a local pharmacy database backup';

    public function handle(BackupService $backups): int
    {
        try {
            $row = $backups->create(
                (string) $this->argument('target'),
                $this->option('drive') !== null ? (string) $this->option('drive') : null,
                (string) $this->option('label'),
            );
        } catch (BackupException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($row['path']);

        return self::SUCCESS;
    }
}
