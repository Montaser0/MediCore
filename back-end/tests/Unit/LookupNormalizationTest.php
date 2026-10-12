<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Support\Numbering\DailyNumber;
use App\Core\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class LookupNormalizationTest extends TestCase
{
    public function test_phone_tail_matches_local_and_international_forms(): void
    {
        $this->assertSame('991234567', PhoneNumber::tail('0991 234 567'));
        $this->assertSame('991234567', PhoneNumber::tail('+963991234567'));
        $this->assertSame('991234567', PhoneNumber::tail('٠٠٩٦٣٩٩١٢٣٤٥٦٧'));
        $this->assertNull(PhoneNumber::tail('   '));
    }

    public function test_daily_serial_reads_the_desk_number(): void
    {
        $this->assertSame(15, DailyNumber::serial('2026-10-12-15'));
        $this->assertSame(15, DailyNumber::serial('MRN-2026-000015'));
        $this->assertSame(15, DailyNumber::serial('15'));
        $this->assertNull(DailyNumber::serial('أحمد'));
        $this->assertNull(DailyNumber::serial('0991234567'));
    }
}
