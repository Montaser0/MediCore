<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Laboratory\Application\Support\LabBarcode;
use PHPUnit\Framework\TestCase;

final class LabBarcodeTest extends TestCase
{
    public function test_check_digit_and_arabic_digits(): void
    {
        $this->assertSame('1', LabBarcode::checkDigit('400638133393'));
        $this->assertTrue(LabBarcode::valid('4006381333931'));
        $this->assertFalse(LabBarcode::valid('4006381333932'));
        $this->assertSame('6', LabBarcode::checkDigit('230000000001'));
        $this->assertTrue(LabBarcode::valid('2300000000016'));
        $arabic = "\u{0662}\u{0663}"
            ."\u{0660}\u{0660}\u{0660}\u{0660}\u{0660}\u{0660}\u{0660}\u{0660}\u{0660}"
            ."\u{0661}\u{0666}";
        $this->assertSame('2300000000016', LabBarcode::digits($arabic));
        $this->assertSame('2300000000016', LabBarcode::digits('23 00000000016'));
    }
}
