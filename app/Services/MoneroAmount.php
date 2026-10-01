<?php

namespace App\Services;

use InvalidArgumentException;

final class MoneroAmount
{
    public const ATOMIC_UNITS_PER_XMR = 1_000_000_000_000;

    public static function toAtomic(string|int|float $xmr): int
    {
        $value = trim((string) $xmr);
        if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,12})?$/', $value)) {
            throw new InvalidArgumentException('Invalid Monero amount.');
        }
        [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
        $fraction=str_pad($fraction,12,'0');
        $wholeUnits=self::safeMultiply((int)$whole,self::ATOMIC_UNITS_PER_XMR);
        $atomic=$wholeUnits+(int)$fraction;
        if($atomic<0) throw new InvalidArgumentException('Monero amount cannot be negative.');
        return $atomic;
    }

    public static function fromAtomic(int $atomic): string
    {
        if($atomic<0) throw new InvalidArgumentException('Atomic units cannot be negative.');
        $whole=intdiv($atomic,self::ATOMIC_UNITS_PER_XMR);
        $fraction=$atomic%self::ATOMIC_UNITS_PER_XMR;
        return sprintf('%d.%012d',$whole,$fraction);
    }

    public static function percentOf(int $atomic,string $percent): int
    {
        $percent=trim($percent);
        if($atomic<0 || !preg_match('/^(?:0|\d+)(?:\.\d{1,4})?$/',$percent)) throw new InvalidArgumentException('Invalid fee calculation.');
        [$whole,$fraction]=array_pad(explode('.',$percent,2),2,'');
        $basis=((int)$whole*10000)+(int)str_pad($fraction,4,'0');
        $fee=intdiv(($atomic*$basis)+5000,10000*100);
        if($atomic>0 && $basis>0 && $fee===0) return 1;
        return $fee;
    }

    private static function safeMultiply(int $a,int $b): int
    {
        if($a<0 || $b<0 || $a > intdiv(PHP_INT_MAX,$b)) throw new InvalidArgumentException('Monero amount is too large.');
        return $a*$b;
    }
}
