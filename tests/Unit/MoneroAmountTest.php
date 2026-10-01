<?php

namespace Tests\Unit;

use App\Services\MoneroAmount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneroAmountTest extends TestCase
{
    public function test_atomic_round_trip_preserves_twelve_decimals(): void
    {
        $atomic=MoneroAmount::toAtomic('1.123456789012');
        $this->assertSame('1123456789012',$atomic);
        $this->assertSame('1.123456789012',MoneroAmount::fromAtomic($atomic));
    }

    public function test_fee_is_calculated_without_float_rounding(): void
    {
        $atomic=MoneroAmount::toAtomic('10.000000000000');
        $fee=MoneroAmount::percentOf($atomic,'3.00');
        $this->assertSame('30000000000',$fee);
    }

    public function test_subtraction_cannot_make_a_wallet_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MoneroAmount::sub('10','11');
    }
}
