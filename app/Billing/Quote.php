<?php

namespace App\Billing;

/**
 * What a generation will cost the user, before Pixelforge has reported the real token usage.
 */
final readonly class Quote
{
    public function __construct(
        public int $estimatedPriceNanos,
        public int $maxPriceNanos,
    ) {}
}
