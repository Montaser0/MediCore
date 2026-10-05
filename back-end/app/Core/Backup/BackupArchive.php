<?php

declare(strict_types=1);

namespace App\Core\Backup;

/**
 * A pharmacy backup is a gzipped SQL dump with a short header.
 * The header is what stops a random or newer file from being restored.
 */
final class BackupArchive
{
    public const MARKER = 'HMS-PHARMACY-BACKUP';

    public const FORMAT = 1;

    public static function header(string $appVersion, \DateTimeInterface $createdAt): string
    {
        return implode("\n", [
            '-- '.self::MARKER,
            '-- format: '.self::FORMAT,
            '-- edition: pharmacy',
            '-- app_version: '.$appVersion,
            '-- created_at: '.$createdAt->format('c'),
            '',
            '',
        ]);
    }

    public static function assertCompatible(string $path, string $installedVersion): void
    {
        if (! is_file($path)) {
            throw new BackupException('ملف النسخة الاحتياطية غير موجود.');
        }

        $magic = file_get_contents($path, false, null, 0, 2);

        if ($magic !== "\x1f\x8b") {
            throw new BackupException('الملف ليس نسخة احتياطية مضغوطة صالحة.');
        }

        $gz = @gzopen($path, 'rb');

        if ($gz === false) {
            throw new BackupException('تعذر فتح ملف النسخة الاحتياطية.');
        }

        $header = '';
        $checked = false;

        while (! gzeof($gz)) {
            $chunk = gzread($gz, 8192);

            if ($chunk === false) {
                gzclose($gz);
                throw new BackupException('ملف النسخة الاحتياطية تالف.');
            }

            if (! $checked) {
                $header .= $chunk;

                try {
                    self::assertHeader($header, $installedVersion);
                } catch (BackupException $exception) {
                    gzclose($gz);
                    throw $exception;
                }

                $checked = true;
            }
        }

        $closed = gzclose($gz);

        if ($closed === false) {
            throw new BackupException('ملف النسخة الاحتياطية تالف أو غير مكتمل.');
        }

        if (! $checked) {
            throw new BackupException('ملف النسخة الاحتياطية فارغ.');
        }
    }

    private static function assertHeader(string $header, string $installedVersion): void
    {
        $lines = preg_split("/\r\n|\n|\r/", substr($header, 0, 800)) ?: [];

        if (! isset($lines[0]) || trim($lines[0]) !== '-- '.self::MARKER) {
            throw new BackupException('هذا الملف ليس نسخة احتياطية لنظام HMS Pharmacy.');
        }

        $map = [];

        foreach ($lines as $line) {
            if (preg_match('/^-- ([a-z_]+): (.+)$/', trim($line), $matches) === 1) {
                $map[$matches[1]] = trim($matches[2]);
            }
        }

        if ((int) ($map['format'] ?? 0) !== self::FORMAT) {
            throw new BackupException('إصدار ملف النسخة الاحتياطية غير متوافق مع هذا النظام.');
        }

        if (($map['edition'] ?? '') !== 'pharmacy') {
            throw new BackupException('هذه النسخة الاحتياطية ليست لنسخة الصيدلية.');
        }

        $backupVersion = (string) ($map['app_version'] ?? '0.0.0');

        if (self::major($backupVersion) > self::major($installedVersion)) {
            throw new BackupException('هذه النسخة الاحتياطية أُنشئت بإصدار أحدث من النظام المثبّت.');
        }
    }

    private static function major(string $version): int
    {
        $parts = explode('.', $version);

        return (int) ($parts[0] ?? 0);
    }
}
