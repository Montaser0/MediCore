<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Backup\BackupArchive;
use App\Core\Backup\BackupException;
use PHPUnit\Framework\TestCase;

final class BackupArchiveTest extends TestCase
{
    public function test_accepts_a_pharmacy_backup_from_the_same_major_version(): void
    {
        $path = $this->gzip(BackupArchive::header('1.0.0', new \DateTimeImmutable('2026-10-05T02:00:00+03:00'))."SELECT 1;\n");

        BackupArchive::assertCompatible($path, '1.2.0');

        $this->assertFileExists($path);
        @unlink($path);
    }

    public function test_rejects_another_edition(): void
    {
        $header = "-- HMS-PHARMACY-BACKUP\n-- format: 1\n-- edition: hospital\n-- app_version: 1.0.0\n";
        $path = $this->gzip($header);

        $this->expectException(BackupException::class);
        BackupArchive::assertCompatible($path, '1.0.0');
    }

    public function test_rejects_a_newer_format(): void
    {
        $header = "-- HMS-PHARMACY-BACKUP\n-- format: 9\n-- edition: pharmacy\n-- app_version: 1.0.0\n";
        $path = $this->gzip($header);

        $this->expectException(BackupException::class);
        BackupArchive::assertCompatible($path, '1.0.0');
    }

    public function test_rejects_a_newer_application_major(): void
    {
        $path = $this->gzip(BackupArchive::header('2.0.0', new \DateTimeImmutable('2026-10-05T02:00:00+03:00')));

        $this->expectException(BackupException::class);
        BackupArchive::assertCompatible($path, '1.0.0');
    }

    public function test_rejects_a_corrupt_gzip_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'hms-bad-');
        $this->assertNotFalse($path);
        file_put_contents($path, "\x1f\x8b"."not-a-real-gzip");

        $this->expectException(BackupException::class);
        BackupArchive::assertCompatible($path, '1.0.0');
    }

    private function gzip(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hms-ok-');
        $this->assertNotFalse($path);
        $gz = gzopen($path, 'wb9');
        $this->assertNotFalse($gz);
        gzwrite($gz, $contents);
        gzclose($gz);

        return $path;
    }
}
