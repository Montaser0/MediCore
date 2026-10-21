<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Excel\Xlsx;
use Tests\TestCase;

final class XlsxRoundTripTest extends TestCase
{
    public function test_arabic_text_survives_a_workbook(): void
    {
        $path = Xlsx::write([
            ['الاسم', 'السعر'],
            ['تحليل سكر', '1500'],
        ], 'التحاليل');

        try {
            $rows = Xlsx::read($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame('الاسم', $rows[0]['cells'][0]);
        $this->assertSame('السعر', $rows[0]['cells'][1]);
        $this->assertSame(1, $rows[0]['row']);
        $this->assertSame('تحليل سكر', $rows[1]['cells'][0]);
        $this->assertSame('1500', $rows[1]['cells'][1]);
        $this->assertSame(2, $rows[1]['row']);
    }
}
