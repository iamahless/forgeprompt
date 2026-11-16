<?php

namespace App\Billing;

final class Markup
{
    /**
     * Turn what the provider charges us into what the user pays.
     */
    public static function apply(int $providerCostNanos): int
    {
        $percent = 100 + (int) config('billing.markup_percent');

        // A fraction of a nanodollar rounds up, so the markup is never short.
        return intdiv($providerCostNanos * $percent + 99, 100);
    }
}
