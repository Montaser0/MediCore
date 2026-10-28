<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Pharmacy\Application\Support\PharmacyProfit;
use PHPUnit\Framework\TestCase;

final class PharmacyProfitTest extends TestCase
{
    public function test_discount_is_shared_and_a_return_gives_the_cost_back(): void
    {
        $this->assertSame(50.0, PharmacyProfit::onSold(100, 100, 80, 30));
        $this->assertSame(25.0, PharmacyProfit::onReturned(100, 100, 80, 30, 2, 1));
    }

    public function test_a_free_line_does_not_invent_profit(): void
    {
        $this->assertSame(0.0, PharmacyProfit::netOf(40, 0, 0));
        $this->assertSame(-10.0, PharmacyProfit::onSold(40, 0, 0, 10));
    }
};
