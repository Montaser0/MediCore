<?php

declare(strict_types=1);

namespace App\Core\Backup;

use Illuminate\Console\Command;

final class AutomaticBackupCommand extends Command
{
    protected $signature = 'hms:backup-auto';

    protected $description = 'Save today\'s pharmacy backup on the USB drive and delete older copies';

    public function handle(BackupService $backups): int
    {
        $ran = $backups->runScheduled();
        $this->line($ran ? 'saved' : 'waiting');

        return self::SUCCESS;
    }
}
