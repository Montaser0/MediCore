<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Pharmacy\Application\Support\MedicinePack;
use PHPUnit\Framework\TestCase;

final class MedicinePackTest extends TestCase
{
    public function test_a_box_and_a_strip_become_tablets_and_keep_their_prices(): void
    {
        $this->assertSame(200.0, MedicinePack::toBase(2, MedicinePack::BOX, 10, 10));
        $this->assertSame(30.0, MedicinePack::toBase(3, MedicinePack::STRIP, 10, 10));
        $this->assertSame(100.0, MedicinePack::costPerBase(10000, MedicinePack::BOX, 10, 10));
        $this->assertSame(1000.0, MedicinePack::sellPrice(10000, 0, 0, MedicinePack::STRIP, 10, 10));
        $this->assertSame(1200.0, MedicinePack::sellPrice(10000, 1200, 0, MedicinePack::STRIP, 10, 10));
        $this->assertSame(120.0, MedicinePack::sellPrice(10000, 1200, 0, MedicinePack::TABLET, 10, 10));
    }
}
