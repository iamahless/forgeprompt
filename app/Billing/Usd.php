<?php

namespace App\Billing;

use InvalidArgumentException;

/**
 * Money is held as whole nanodollars (1e-9 USD), the precision Pixelforge bills in, so no amount is ever rounded.
 */
final class Usd
{
    public const int NANOS_PER_DOLLAR = 1_000_000_000;

    /**
     * Parse an exact decimal string such as "0.046125000" without going through a float.
     */
    public static function toNanos(string $decimal): int
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,9}))?$/', $decimal, $matches) !== 1) {
            throw new InvalidArgumentException("[{$decimal}] is not a dollar amount with at most nine decimal places.");
        }

        return (int) $matches[1] * self::NANOS_PER_DOLLAR + (int) str_pad($matches[2] ?? '', 9, '0');
    }

    /**
     * Format as an exact dollar amount, keeping at least two decimals: 46125000 becomes "$0.046125".
     */
    public static function format(int $nanos): string
    {
        $decimal = sprintf('%d.%09d', intdiv(abs($nanos), self::NANOS_PER_DOLLAR), abs($nanos) % self::NANOS_PER_DOLLAR);
        $decimal = rtrim($decimal, '0');
        $decimal = str_pad($decimal, strpos($decimal, '.') + 3, '0');

        return ($nanos < 0 ? '-$' : '$').$decimal;
    }
}
