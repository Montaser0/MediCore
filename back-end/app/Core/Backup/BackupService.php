<?php

declare(strict_types=1);

namespace App\Core\Backup;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

/**
 * Local SQL backups for the pharmacy station. The database stays on this
 * computer. Copies go to the station folder or to a USB drive.
 */
final class BackupService
{
    private const FILE_PATTERN = '/^(pharmacy|pre-upgrade)-\d{4}-\d{2}-\d{2}-\d{4}(?:-\d{2})?\.sql\.gz$/';

    /**
     * @return array{name: string, path: string, location: string, drive: string|null, size: int, created_at: string}
     */
    public function create(string $target, ?string $drive = null, string $label = 'pharmacy'): array
    {
        $this->assertLabel($label);
        $directory = $this->directory($target, $drive);
        $this->ensureDirectory($directory);
        $lock = $this->lock();

        try {
            $name = $this->filename($directory, $label);
            $path = $directory.DIRECTORY_SEPARATOR.$name;
            $this->dump($path);
            BackupArchive::assertCompatible($path, $this->version());

            return $this->describe($path, $target, $drive);
        } finally {
            $this->unlock($lock);
        }
    }

    /**
     * @return list<array{name: string, path: string, location: string, drive: string|null, size: int, created_at: string}>
     */
    public function history(string $target, ?string $drive = null): array
    {
        $directory = $this->directory($target, $drive);

        if (! is_dir($directory)) {
            return [];
        }

        $rows = [];

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [] as $path) {
            if (in_array(basename($path), ['.', '..'], true)) {
                continue;
            }

            if (preg_match(self::FILE_PATTERN, basename($path)) !== 1) {
                continue;
            }

            $rows[] = $this->describe($path, $target, $drive);
        }

        usort($rows, static fn (array $left, array $right): int => strcmp($right['created_at'], $left['created_at']));

        return $rows;
    }

    private function forgetOlder(string $letter, int $keep): void
    {
        $names = [];

        foreach ($this->history('usb', $letter) as $row) {
            if (str_starts_with($row['name'], 'pharmacy-')) {
                $names[] = $row['name'];
            }
        }

        foreach (BackupSchedule::expired($names, $keep) as $name) {
            $path = $this->directory('usb', $letter).DIRECTORY_SEPARATOR.$name;

            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return array{time: string, keep: int, last_on: string|null, retry_after: string|null}
     */
    private function readSchedule(): array
    {
        $defaults = [
            'time' => '08:00',
            'keep' => 7,
            'last_on' => null,
            'retry_after' => null,
        ];
        $path = $this->schedulePath();

        if (! is_file($path)) {
            return $defaults;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return $defaults;
        }

        return [
            'time' => '08:00',
            'keep' => max(1, min(30, (int) ($decoded['keep'] ?? $defaults['keep']))),
            'last_on' => is_string($decoded['last_on'] ?? null) ? $decoded['last_on'] : null,
            'retry_after' => is_string($decoded['retry_after'] ?? null) ? $decoded['retry_after'] : null,
        ];
    }

    /**
     * @param  array{time: string, keep: int, last_on: string|null, retry_after: string|null}  $schedule
     */
    private function writeSchedule(array $schedule): void
    {
        $path = $this->schedulePath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($schedule, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function schedulePath(): string
    {
        return storage_path('app/hms-backup-schedule.json');
    }

    private function timezoneName(): string
    {
        $timezone = (string) config('hms.local.backup_timezone', 'Asia/Damascus');

        return $timezone !== '' ? $timezone : 'Asia/Damascus';
    }

    /**
     * @return list<array{letter: string, name: string|null}>
     */
    public function drives(): array
    {
        return $this->listedDrives();
    }

    public function runScheduled(): bool
    {
        $stored = $this->readSchedule();
        $now = new \DateTimeImmutable('now', new \DateTimeZone($this->timezoneName()));

        if (! BackupSchedule::isDue($now, $stored['time'], $stored['last_on'])) {
            return false;
        }

        $retryAfter = $stored['retry_after'];

        if ($retryAfter !== null && $now < new \DateTimeImmutable($retryAfter)) {
            return false;
        }

        $drives = $this->drives();

        if ($drives === []) {
            return false;
        }

        try {
            $created = $this->create('usb', $drives[0]['letter']);
            $this->forgetOlder($drives[0]['letter'], $stored['keep']);
            $fresh = $this->readSchedule();
            $fresh['last_on'] = $now->format('Y-m-d');
            $fresh['retry_after'] = null;
            $this->writeSchedule($fresh);
            logger()->info('backup.automatic', ['file' => $created['name'], 'drive' => $drives[0]['letter']]);
        } catch (BackupException $exception) {
            $fresh = $this->readSchedule();

            if ($fresh['last_on'] !== $now->format('Y-m-d')) {
                $fresh['retry_after'] = $now->modify('+30 minutes')->format('c');
                $this->writeSchedule($fresh);
            }

            logger()->error('backup.automatic', ['message' => $exception->getMessage()]);

            return false;
        }

        return true;
    }

    /**
     * @return array{restored: string}
     */
    public function restore(string $filename, string $target, ?string $drive, bool $confirm): array
    {
        if (! $confirm) {
            throw new BackupException('يلزم تأكيد الاستعادة.');
        }

        $path = $this->resolve($filename, $target, $drive);
        BackupArchive::assertCompatible($path, $this->version());
        $lock = $this->lock();

        try {
            $sql = $this->gunzip($path);

            try {
                $this->import($sql);
            } finally {
                @unlink($sql);
            }

            $this->afterRestore();

            return ['restored' => basename($path)];
        } finally {
            $this->unlock($lock);
        }
    }

    private function dump(string $destination): void
    {
        $plain = $this->temporary('hms-sql-');
        $cnf = $this->clientDefaults();

        try {
            $result = Process::timeout(600)->env($this->windowsEnvironment())->run([
                $this->binary('mysqldump'),
                '--defaults-extra-file='.$this->slash($cnf),
                '--protocol=tcp',
                '--single-transaction',
                '--skip-lock-tables',
                '--default-character-set=utf8mb4',
                '--hex-blob',
                '--add-drop-table',
                '--result-file='.$this->slash($plain),
                $this->database(),
            ]);

            if (! $result->successful() || ! is_file($plain) || filesize($plain) === 0) {
                logger()->error('backup.dump', [
                    'exit' => $result->exitCode(),
                    'err' => $result->errorOutput(),
                    'out' => $result->output(),
                    'file' => is_file($plain) ? filesize($plain) : null,
                ]);
                throw new BackupException('تعذر إنشاء النسخة الاحتياطية.');
            }

            $timezone = new \DateTimeZone((string) config('hms.local.backup_timezone', 'Asia/Damascus'));
            $header = BackupArchive::header($this->version(), new \DateTimeImmutable('now', $timezone));
            $out = gzopen($destination, 'wb6');

            if ($out === false) {
                throw new BackupException('تعذر كتابة ملف النسخة الاحتياطية.');
            }

            gzwrite($out, $header);
            $in = fopen($plain, 'rb');

            if ($in === false) {
                gzclose($out);
                throw new BackupException('تعذر قراءة قاعدة البيانات المصدّرة.');
            }

            while (! feof($in)) {
                $chunk = fread($in, 1024 * 1024);

                if ($chunk === false) {
                    break;
                }

                gzwrite($out, $chunk);
            }

            fclose($in);
            gzclose($out);
        } finally {
            @unlink($plain);
            @unlink($cnf);
        }
    }

    private function import(string $sqlPath): void
    {
        $cnf = $this->clientDefaults();

        try {
            $process = proc_open(
                [
                    $this->binary('mysql'),
                    '--defaults-extra-file='.$this->slash($cnf),
                    '--protocol=tcp',
                    '--binary-mode',
                    $this->database(),
                ],
                [
                    0 => ['file', $sqlPath, 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                null,
                $this->windowsEnvironment(),
                ['bypass_shell' => true],
            );

            if (! is_resource($process)) {
                throw new BackupException('تعذر تشغيل أداة الاستعادة.');
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);

            if ($code !== 0) {
                throw new BackupException('فشلت استعادة قاعدة البيانات.');
            }
        } finally {
            @unlink($cnf);
        }
    }

    private function gunzip(string $path): string
    {
        $sql = $this->temporary('hms-restore-');
        $in = gzopen($path, 'rb');
        $out = fopen($sql, 'wb');

        if ($in === false || $out === false) {
            if (is_resource($in)) {
                gzclose($in);
            }
            if (is_resource($out)) {
                fclose($out);
            }
            throw new BackupException('تعذر فتح ملف النسخة الاحتياطية.');
        }

        while (! gzeof($in)) {
            $chunk = gzread($in, 1024 * 1024);

            if ($chunk === false) {
                gzclose($in);
                fclose($out);
                @unlink($sql);
                throw new BackupException('ملف النسخة الاحتياطية تالف.');
            }

            fwrite($out, $chunk);
        }

        $closed = gzclose($in);
        fclose($out);

        if ($closed === false) {
            @unlink($sql);
            throw new BackupException('ملف النسخة الاحتياطية تالف أو غير مكتمل.');
        }

        return $sql;
    }

    private function afterRestore(): void
    {
        $script = (string) config('hms.local.control_script');

        if ($script !== '' && is_file($script)) {
            $command = 'cmd.exe /c start "" /MIN powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File '.escapeshellarg($script);
            $handle = popen($command, 'r');

            if (is_resource($handle)) {
                pclose($handle);
            }

            return;
        }

        Artisan::call('optimize:clear');
    }

    private function clientDefaults(): string
    {
        $connection = config('database.connections.mysql');

        if (! is_array($connection)) {
            throw new BackupException('قاعدة البيانات المحلية غير مهيأة.');
        }

        $path = $this->temporary('hms-cnf-');
        $ini = "[client]\n"
            .'host='.(string) ($connection['host'] ?? '127.0.0.1')."\n"
            .'port='.(string) ($connection['port'] ?? '3306')."\n"
            .'user='.(string) ($connection['username'] ?? '')."\n"
            .'password='.(string) ($connection['password'] ?? '')."\n";

        if (file_put_contents($path, $ini) === false) {
            throw new BackupException('تعذر تجهيز اتصال قاعدة البيانات.');
        }

        return $path;
    }

    private function binary(string $tool): string
    {
        $path = (string) config('hms.local.'.$tool);

        if ($path !== '' && is_file($path)) {
            return $path;
        }

        foreach ($this->candidateBinaries($tool) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new BackupException('أداة قاعدة البيانات غير متوفرة على هذا الجهاز.');
    }

    /**
     * @return list<string>
     */
    private function candidateBinaries(string $tool): array
    {
        $candidates = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates[] = 'C:\\xampp\\mysql\\bin\\'.$tool.'.exe';
        }

        $path = (string) getenv('PATH');
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $directory = rtrim($directory, '\\/');
            if ($directory === '') {
                continue;
            }

            $candidates[] = $directory.DIRECTORY_SEPARATOR.$tool.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        }

        return $candidates;
    }

    private function database(): string
    {
        $name = (string) config('database.connections.mysql.database');

        if ($name === '') {
            throw new BackupException('اسم قاعدة البيانات غير معروف.');
        }

        return $name;
    }

    private function version(): string
    {
        $version = (string) config('hms.local.version', '1.0.0');

        return $version !== '' ? $version : '1.0.0';
    }

    private function directory(string $target, ?string $drive): string
    {
        if ($target === 'local') {
            $configured = (string) config('hms.local.backup_dir');

            return $configured !== '' ? $configured : storage_path('app/hms-backups');
        }

        if ($target !== 'usb') {
            throw new BackupException('مكان النسخة الاحتياطية غير معروف.');
        }

        $letter = strtoupper((string) $drive);

        if (preg_match('/^[A-Z]$/', $letter) !== 1 || ! in_array($letter, $this->removableDrives(), true)) {
            throw new BackupException('صل ذاكرة USB ثم أعد المحاولة.');
        }

        return $letter.':\\HMS-Backups';
    }

    private function resolve(string $filename, string $target, ?string $drive): string
    {
        if (preg_match(self::FILE_PATTERN, $filename) !== 1 || basename($filename) !== $filename) {
            throw new BackupException('اسم ملف النسخة الاحتياطية غير مقبول.');
        }

        $directory = $this->directory($target, $drive);
        $base = realpath($directory);
        $path = realpath($directory.DIRECTORY_SEPARATOR.$filename);

        if ($base === false || $path === false || ! str_starts_with(strtolower($path), strtolower($base))) {
            throw new BackupException('ملف النسخة الاحتياطية غير موجود.');
        }

        return $path;
    }

    /**
     * @return array{name: string, path: string, location: string, drive: string|null, size: int, created_at: string}
     */
    private function describe(string $path, string $target, ?string $drive): array
    {
        $timezone = new \DateTimeZone((string) config('hms.local.backup_timezone', 'Asia/Damascus'));
        $created = (new \DateTimeImmutable('@'.filemtime($path)))->setTimezone($timezone);

        return [
            'name' => basename($path),
            'path' => $path,
            'location' => $target,
            'drive' => $target === 'usb' ? strtoupper((string) $drive) : null,
            'size' => (int) filesize($path),
            'created_at' => $created->format('c'),
        ];
    }

    private function filename(string $directory, string $label): string
    {
        $timezone = new \DateTimeZone((string) config('hms.local.backup_timezone', 'Asia/Damascus'));
        $stamp = (new \DateTimeImmutable('now', $timezone))->format('Y-m-d-Hi');
        $name = $label.'-'.$stamp.'.sql.gz';

        if (is_file($directory.DIRECTORY_SEPARATOR.$name)) {
            $name = $label.'-'.$stamp.'-'.(new \DateTimeImmutable('now', $timezone))->format('s').'.sql.gz';
        }

        return $name;
    }

    private function assertLabel(string $label): void
    {
        if (! in_array($label, ['pharmacy', 'pre-upgrade'], true)) {
            throw new BackupException('نوع النسخة الاحتياطية غير معروف.');
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new BackupException('تعذر إنشاء مجلد النسخ الاحتياطي.');
        }
    }

    /**
     * @var list<array{letter: string, name: string|null}>|null
     */
    private ?array $driveCache = null;

    /**
     * @return list<string>
     */
    private function removableDrives(): array
    {
        return array_map(
            static fn (array $drive): string => $drive['letter'],
            $this->listedDrives(),
        );
    }

    /**
     * @return list<array{letter: string, name: string|null}>
     */
    private function listedDrives(): array
    {
        if ($this->driveCache !== null) {
            return $this->driveCache;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return $this->driveCache = [];
        }

        return $this->driveCache = $this->drivesFromCom()
            ?? $this->drivesFromFsutil()
            ?? $this->drivesFromPowerShell();
    }

    /**
     * @return list<array{letter: string, name: string|null}>|null
     */
    private function drivesFromCom(): ?array
    {
        if (! class_exists(\COM::class)) {
            return null;
        }

        try {
            $wmi = new \COM('winmgmts:{impersonationLevel=impersonate}!\\\\.\\root\\cimv2');
            $disks = $wmi->ExecQuery('SELECT DeviceID, VolumeName FROM Win32_LogicalDisk WHERE DriveType=2');
            $drives = [];

            foreach ($disks as $disk) {
                $letter = $this->driveLetter((string) $disk->DeviceID);

                if ($letter === null || isset($drives[$letter])) {
                    continue;
                }

                $name = trim((string) $disk->VolumeName);
                $drives[$letter] = ['letter' => $letter, 'name' => $name !== '' ? $name : null];
            }

            return array_values($drives);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{letter: string, name: string|null}>|null
     */
    private function drivesFromFsutil(): ?array
    {
        $drives = [];
        $sawOutput = false;

        foreach (range('D', 'Z') as $letter) {
            if (! is_dir($letter.':\\')) {
                continue;
            }

            $output = shell_exec('fsutil fsinfo drivetype '.$letter.':');

            if (! is_string($output) || trim($output) === '') {
                continue;
            }

            $sawOutput = true;

            if (preg_match('/removable|قابل للإزالة|قابل للازالة/ui', $output) === 1) {
                $drives[] = ['letter' => $letter, 'name' => $this->volumeName($letter)];
            }
        }

        return $sawOutput ? $drives : null;
    }

    /**
     * @return list<array{letter: string, name: string|null}>
     */
    private function drivesFromPowerShell(): array
    {
        $result = Process::timeout(20)->env($this->windowsEnvironment())->run([
            'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
            '-NoProfile',
            '-NonInteractive',
            '-Command',
            'Get-CimInstance Win32_LogicalDisk -Filter "DriveType=2" | ForEach-Object { $_.DeviceID + "|" + $_.VolumeName }',
        ]);

        if (! $result->successful()) {
            return [];
        }

        $drives = [];

        foreach (preg_split("/\r\n|\n|\r/", trim($result->output())) ?: [] as $line) {
            [$device, $name] = array_pad(explode('|', trim($line), 2), 2, '');
            $letter = $this->driveLetter($device);

            if ($letter === null || isset($drives[$letter])) {
                continue;
            }

            $name = trim($name);
            $drives[$letter] = ['letter' => $letter, 'name' => $name !== '' ? $name : null];
        }

        return array_values($drives);
    }

    /**
     * @return array<string, string>
     */
    private function windowsEnvironment(): array
    {
        $root = getenv('SystemRoot') ?: 'C:\\Windows';

        return [
            'SystemRoot' => $root,
            'WINDIR' => getenv('WINDIR') ?: $root,
            'PATH' => getenv('PATH') ?: $root.'\\System32',
            'COMSPEC' => getenv('COMSPEC') ?: $root.'\\System32\\cmd.exe',
            'TEMP' => getenv('TEMP') ?: sys_get_temp_dir(),
            'TMP' => getenv('TMP') ?: sys_get_temp_dir(),
            'PATHEXT' => getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD',
        ];
    }

    private function driveLetter(string $value): ?string
    {
        $value = strtoupper(trim($value));

        if (preg_match('/^([A-Z]):$/', $value, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function volumeName(string $letter): ?string
    {
        $output = shell_exec('cmd /c vol '.$letter.':');

        if (! is_string($output)) {
            return null;
        }

        if (preg_match('/\bis ([^\r\n]+)/u', $output, $matches) !== 1) {
            return null;
        }

        $name = trim($matches[1]);

        if ($name === '' || preg_match('/no label/i', $name) === 1) {
            return null;
        }

        return $name;
    }

    /**
     * @return resource
     */
    private function lock()
    {
        $path = storage_path('framework/backup.lock');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $handle = fopen($path, 'c');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new BackupException('هناك عملية نسخ أو استعادة قيد التنفيذ.');
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    private function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function temporary(string $prefix): string
    {
        $directory = storage_path('framework');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = tempnam($directory, $prefix);

        if ($path === false) {
            throw new BackupException('تعذر إنشاء ملف مؤقت.');
        }

        return $path;
    }

    private function slash(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
