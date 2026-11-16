<?php

namespace App\Pixelforge;

/**
 * The current rates and token rules from GET /v1/pricing, used to estimate a generation before it runs.
 */
final readonly class Pricing
{
    /**
     * @param  array<string, mixed>  $payload  The GET /v1/pricing response.
     */
    public function __construct(private array $payload) {}

    /**
     * What Pixelforge is likely to charge for a generation, and the most it could charge, in nanodollars.
     *
     * @return array{expected: int, max: int}
     */
    public function estimate(string $prompt, string $size, string $quality, int $referenceImages): array
    {
        $rules = $this->payload['tokens'];
        $rates = Rates::fromArray($this->payload['rates']);

        $fixed = [
            'input_image_tokens' => $referenceImages * $rules['per_reference_image'],
            'output_tokens' => $rules['output'][$quality][$size],
        ];

        // Text and reasoning tokens are only known once the generation completes.
        $textTokens = (int) ceil(mb_strlen($prompt) / $rules['characters_per_text_token']);
        $textVariancePercent = (int) round($rules['text_token_variance'] * 100);
        $reasoning = $rules['reasoning'][$quality];

        return [
            'expected' => $rates->cost($fixed + [
                'input_text_tokens' => $textTokens,
                'reasoning_tokens' => intdiv($reasoning['min'] + $reasoning['max'], 2),
            ]),
            'max' => $rates->cost($fixed + [
                'input_text_tokens' => intdiv($textTokens * (100 + $textVariancePercent) + 99, 100),
                'reasoning_tokens' => $reasoning['max'],
            ]),
        ];
    }
}
