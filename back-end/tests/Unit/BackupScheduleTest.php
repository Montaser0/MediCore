<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Backup\BackupSchedule;
use PHPUnit\Framework\TestCase;

final class BackupScheduleTest extends TestCase
{
    public function test_runs_after_the_chosen_time_when_today_has_no_copy(): void
    {
        $now = new \DateTimeImmutable('2026-10-26 16:16:00', new \DateTimeZone('Asia/Damascus'));

        $this->assertTrue(BackupSchedule::isDue($now, '02:00', null));
        $this->assertTrue(BackupSchedule::isDue($now, '02:00', '2026-10-25'));
    }

    public function test_waits_until_the_chosen_time_and_skips_a_finished_day(): void
    {
        $now = new \DateTimeImmutable('2026-10-26 01:15:00', new \DateTimeZone('Asia/Damascus'));

        $this->assertFalse(BackupSchedule::isDue($now, '02:00', null));
        $this->assertFalse(BackupSchedule::isDue($now->setTime(16, 0), '02:00', '2026-10-26'));
        $this->assertFalse(BackupSchedule::isDue($now->setTime(16, 0), '25:99', null));
    }

    public function test_drops_only_copies_beyond_the_kept_count(): void
    {
        $names = ['newest', 'middle', 'oldest'];

        $this->assertSame(['oldest'], BackupSchedule::expired($names, 2));
        $this->assertSame([], BackupSchedule::expired($names, 7));
        $this->assertSame(['middle', 'oldest'], BackupSchedule::expired($names, 0));
    }
}
