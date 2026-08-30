<?php

namespace Tests\Unit;

use App\Services\BitcoinAmount;
use PHPUnit\Framework\TestCase;

class BitcoinAmountTest extends TestCase
{
    public function test_btc_is_converted_to_exact_satoshis(): void
    {
        $this->assertSame(1, BitcoinAmount::toSatoshis('0.00000001'));
        $this->assertSame(12345678, BitcoinAmount::toSatoshis('0.12345678'));
    }

    public function test_satoshis_are_formatted_to_eight_decimal_places(): void
    {
        $this->assertSame('0.00000001', BitcoinAmount::fromSatoshis(1));
        $this->assertSame('1.23456789', BitcoinAmount::fromSatoshis(123456789));
    }

    public function test_three_percent_fee_is_calculated_in_integer_satoshis(): void
    {
        $this->assertSame(300000, BitcoinAmount::percentOf(10_000_000, '3.00'));
        $this->assertSame(1, BitcoinAmount::percentOf(1, '3.00'));
    }
}
