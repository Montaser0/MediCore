<?php

declare(strict_types=1);

namespace App\Core\Backup;

use Illuminate\Support\Facades\Cache;

/**
 * The pharmacy computer has no separate clock. While the application is
 * open, one check per minute starts the USB backup if today's time has passed.
 */
final class BackupKick
{
    public static function afterRequest(): void
    {
        if (app()->runningInConsole() || PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        try {
            if (! Cache::add('hms.backup.kick', '1', 60)) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        self::spawn();
    }

    private static function spawn(): void
    {
        $root = getenv('SystemRoot') ?: 'C:\\Windows';
        $env = array_merge(getenv() ?: [], [
            'SystemRoot' => $root,
            'WINDIR' => getenv('WINDIR') ?: $root,
            'COMSPEC' => getenv('COMSPEC') ?: $root.'\\System32\\cmd.exe',
        ]);

        $command = 'start "" /B /D '.self::quote(base_path()).' '.self::quote(PHP_BINARY).' artisan hms:backup-auto';
        $process = proc_open(
            ['cmd.exe', '/c', $command],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            base_path(),
            $env,
            ['bypass_shell' => true],
        );

        if (! is_resource($process)) {
            return;
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);
    }

    private static function quote(string $value): string
    {
        return '"'.str_replace('"', '', $value).'"';
    }
}
