<?php

namespace App\Services;

use InvalidArgumentException;

final class BitcoinAmount
{
    public const SATOSHIS_PER_BTC = 100_000_000;

    public static function toSatoshis(string|int|float $btc): int
    {
        $value = trim((string) $btc);
        if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,8})?$/', $value)) {
            throw new InvalidArgumentException('Invalid Bitcoin amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, 8, '0');
        $sats = ((int) $whole * self::SATOSHIS_PER_BTC) + (int) $fraction;
        if ($sats < 0) {
            throw new InvalidArgumentException('Bitcoin amount cannot be negative.');
        }
        return $sats;
    }

    public static function fromSatoshis(int $satoshis): string
    {
        if ($satoshis < 0) {
            throw new InvalidArgumentException('Satoshis cannot be negative.');
        }

        $whole = intdiv($satoshis, self::SATOSHIS_PER_BTC);
        $fraction = $satoshis % self::SATOSHIS_PER_BTC;
        return sprintf('%d.%08d', $whole, $fraction);
    }

    public static function percentOf(int $satoshis, string $percent): int
    {
        if ($satoshis < 0 || !preg_match('/^(?:0|\d+)(?:\.\d{1,4})?$/', trim($percent))) {
            throw new InvalidArgumentException('Invalid fee calculation.');
        }

        [$whole, $fraction] = array_pad(explode('.', trim($percent), 2), 2, '');
        $percentBasisPoints = ((int) $whole * 10_000) + (int) str_pad($fraction, 4, '0');
        return intdiv(($satoshis * $percentBasisPoints) + 5000, 10_000 * 100);
    }
}
