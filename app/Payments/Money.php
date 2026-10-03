<?php

namespace App\Payments;

final class Money
{
    /** Convert a validated two-decimal amount such as "750.25" without floating point. */
    public static function minor(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        if (! ctype_digit($whole) || ($fraction !== '' && ! ctype_digit($fraction)) || strlen($fraction) > 2) {
            throw new \InvalidArgumentException('Unsupported amount.');
        }

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
