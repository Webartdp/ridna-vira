<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_values_are_parsed_without_rounding_errors(): void
    {
        $this->assertSame(12550, Money::minor('125,50'));
        $this->assertSame(101, Money::minor('1.01'));
        $this->assertSame(110, Money::minor('1.1'));
        $this->assertSame('125.50', Money::decimal(12550));
        $this->expectException(\InvalidArgumentException::class);
        Money::minor('1.001');
    }
}
