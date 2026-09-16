<?php

namespace Tests\Unit;

use App\Services\BitcoinAmount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BitcoinAmountTest extends TestCase
{
    public function test_btc_is_converted_to_exact_satoshis(): void
    {
        $this->assertSame(1, BitcoinAmount::toSatoshis('0.00000001'));
        $this->assertSame(12345678, BitcoinAmount::toSatoshis('0.12345678'));
        $this->assertSame(100000000, BitcoinAmount::toSatoshis('1'));
    }

    public function test_satoshis_are_formatted_to_eight_decimal_places(): void
    {
        $this->assertSame('0.00000001', BitcoinAmount::fromSatoshis(1));
        $this->assertSame('1.23456789', BitcoinAmount::fromSatoshis(123456789));
        $this->assertSame('0.00000000', BitcoinAmount::fromSatoshis(0));
    }

    public function test_conversion_round_trip_preserves_every_satoshi(): void
    {
        foreach ([0, 1, 21, 12345678, 100000000, 2100000000000000] as $satoshis) {
            $this->assertSame($satoshis, BitcoinAmount::toSatoshis(BitcoinAmount::fromSatoshis($satoshis)));
        }
    }

    public function test_three_percent_fee_is_calculated_in_integer_satoshis(): void
    {
        $this->assertSame(300000, BitcoinAmount::percentOf(10_000_000, '3.00'));
        $this->assertSame(1, BitcoinAmount::percentOf(1, '3.00'));
        $this->assertSame(0, BitcoinAmount::percentOf(10_000_000, '0'));
    }

    /**
     * @dataProvider invalidBtcAmounts
     */
    public function test_invalid_btc_amounts_are_rejected(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        BitcoinAmount::toSatoshis($amount);
    }

    public static function invalidBtcAmounts(): array
    {
        return [
            'negative' => ['-0.1'],
            'too precise' => ['0.000000001'],
            'leading zero' => ['01.0'],
            'trailing decimal' => ['1.'],
            'non numeric' => ['abc'],
        ];
    }

    public function test_negative_satoshis_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BitcoinAmount::fromSatoshis(-1);
    }

    /**
     * @dataProvider invalidFeePercentages
     */
    public function test_invalid_fee_percentages_are_rejected(string $percent): void
    {
        $this->expectException(InvalidArgumentException::class);
        BitcoinAmount::percentOf(1000, $percent);
    }

    public static function invalidFeePercentages(): array
    {
        return [
            'negative' => ['-1'],
            'too precise' => ['3.00001'],
            'non numeric' => ['abc'],
        ];
    }
}
