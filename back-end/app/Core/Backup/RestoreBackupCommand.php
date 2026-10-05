<?php

declare(strict_types=1);

namespace App\Core\Backup;

use Illuminate\Console\Command;

final class RestoreBackupCommand extends Command
{
    protected $signature = 'hms:restore {file} {--location=local} {--drive=} {--force}';

    protected $description = 'Restore a pharmacy database backup';

    public function handle(BackupService $backups): int
    {
        try {
            $row = $backups->restore(
                (string) $this->argument('file'),
                (string) $this->option('location'),
                $this->option('drive') !== null ? (string) $this->option('drive') : null,
                (bool) $this->option('force'),
            );
        } catch (BackupException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($row['restored']);

        return self::SUCCESS;
    }
}
