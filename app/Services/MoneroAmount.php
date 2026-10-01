<?php

namespace App\Services;

use InvalidArgumentException;

final class MoneroAmount
{
    public static function normalize(string|int|float $xmr): string
    {
        $value=trim((string)$xmr);
        if(!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,12})?$/',$value)) throw new InvalidArgumentException('Invalid Monero amount.');
        [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
        return (int)$whole.'.'.str_pad($fraction,12,'0');
    }

    public static function toAtomic(string|int|float $xmr): string
    {
        [$whole,$fraction]=explode('.',self::normalize($xmr),2);
        return ltrim($whole.str_pad($fraction,12,'0'),'0') ?: '0';
    }

    public static function fromAtomic(string|int $atomic): string
    {
        $value=trim((string)$atomic);
        if(!preg_match('/^\d+$/',$value)) throw new InvalidArgumentException('Invalid Monero atomic amount.');
        $value=str_pad($value,13,'0',STR_PAD_LEFT);
        $whole=rtrim(substr($value,0,-12),'0') ?: '0';
        $fraction=rtrim(substr($value,-12),'0');
        return $whole.($fraction===''?'':'.'.$fraction);
    }

    public static function add(string $a,string $b): string { return bcadd($a,$b,0); }
    public static function sub(string $a,string $b): string
    {
        if(bccomp($a,$b,0)<0) throw new InvalidArgumentException('Monero balance cannot be negative.');
        return bcsub($a,$b,0);
    }
    public static function cmp(string $a,string $b): int { return bccomp($a,$b,0); }

    public static function percentOf(string $atomic,string $percent): string
    {
        if(!preg_match('/^(?:0|\d+)(?:\.\d{1,4})?$/',trim($percent))) throw new InvalidArgumentException('Invalid fee calculation.');
        [$whole,$fraction]=array_pad(explode('.',trim($percent),2),2,'');
        $basis=(string)(((int)$whole*10000)+(int)str_pad($fraction,4,'0'));
        $fee=bcdiv(bcmul($atomic,$basis,0),'1000000',0);
        if(bccomp($atomic,'0',0)>0 && bccomp($basis,'0',0)>0 && bccomp($fee,'0',0)===0) return '1';
        return $fee;
    }
}
