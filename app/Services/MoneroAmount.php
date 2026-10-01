<?php

namespace App\Services;

use InvalidArgumentException;

final class MoneroAmount
{
    public const ATOMIC_UNITS_PER_XMR = 1_000_000_000_000;

    public static function toAtomicUnits(string|int|float $xmr): int
    {
        $value = trim((string) $xmr);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,12})?$/', $value)) {
            throw new InvalidArgumentException('Invalid Monero amount.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, 12, '0');
        $units = ((int) $whole * self::ATOMIC_UNITS_PER_XMR) + (int) $fraction;
        if ($units < 0) throw new InvalidArgumentException('Monero amount cannot be negative.');
        return $units;
    }

    public static function fromAtomicUnits(int $units): string
    {
        if ($units < 0) throw new InvalidArgumentException('Atomic Monero units cannot be negative.');
        $whole = intdiv($units, self::ATOMIC_UNITS_PER_XMR);
        $fraction = $units % self::ATOMIC_UNITS_PER_XMR;
        return sprintf('%d.%012d', $whole, $fraction);
    }

    public static function percentOf(int $units, string $percent): int
    {
        $percent = trim($percent);
        if ($units < 0 || ! preg_match('/^(?:0|\d+)(?:\.\d{1,4})?$/', $percent)) {
            throw new InvalidArgumentException('Invalid fee calculation.');
        }
        [$whole, $fraction] = array_pad(explode('.', $percent, 2), 2, '');
        $basisPoints = ((int) $whole * 10_000) + (int) str_pad($fraction, 4, '0');
        $fee = intdiv(($units * $basisPoints) + 5000, 10_000 * 100);
        if ($units > 0 && $basisPoints > 0 && $fee === 0) return 1;
        return $fee;
    }
}
