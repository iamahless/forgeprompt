<?php

namespace App\Actions\Generations;

use App\Billing\Markup;
use App\Billing\Quote;
use App\Pixelforge\Client;
use App\Pixelforge\PixelforgeException;
use Illuminate\Validation\ValidationException;

class QuoteGeneration
{
    public function __construct(private Client $pixelforge) {}

    /**
     * Price a generation before it runs: Pixelforge's estimated cost plus our markup.
     *
     * @throws ValidationException
     */
    public function handle(string $prompt, string $size, string $quality, int $referenceImages): Quote
    {
        try {
            $cost = $this->pixelforge->pricing()->estimate($prompt, $size, $quality, $referenceImages);
        } catch (PixelforgeException) {
            throw ValidationException::withMessages([
                'pricing' => 'We could not price this generation right now. Nothing was charged. Please try again in a moment.',
            ])->status(503);
        }

        return new Quote(Markup::apply($cost['expected']), Markup::apply($cost['max']));
    }
}
