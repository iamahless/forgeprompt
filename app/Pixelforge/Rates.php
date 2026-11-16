<?php

namespace App\Pixelforge;

use App\Billing\Usd;
use InvalidArgumentException;

/**
 * The price of each kind of token, as Pixelforge reports it on a generation and on GET /v1/pricing.
 */
final readonly class Rates
{
    public const array TOKEN_TYPES = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    private const int TOKENS_PER_RATE = 1_000_000;

    /**
     * @param  array<string, int>  $nanosPerToken
     */
    private function __construct(private array $nanosPerToken) {}

    /**
     * @param  array<string, string>  $dollarsPerMillionTokens
     */
    public static function fromArray(array $dollarsPerMillionTokens): self
    {
        $nanosPerToken = [];

        foreach (self::TOKEN_TYPES as $type) {
            $nanosPerMillionTokens = Usd::toNanos($dollarsPerMillionTokens[$type]);

            if ($nanosPerMillionTokens % self::TOKENS_PER_RATE !== 0) {
                throw new InvalidArgumentException("The {$type} rate is finer than a nanodollar per token.");
            }

            $nanosPerToken[$type] = intdiv($nanosPerMillionTokens, self::TOKENS_PER_RATE);
        }

        return new self($nanosPerToken);
    }

    /**
     * The exact cost, in nanodollars, of the given token counts.
     *
     * @param  array<string, int>  $tokens
     */
    public function cost(array $tokens): int
    {
        $nanos = 0;

        foreach (self::TOKEN_TYPES as $type) {
            $nanos += $tokens[$type] * $this->nanosPerToken[$type];
        }

        return $nanos;
    }
}
