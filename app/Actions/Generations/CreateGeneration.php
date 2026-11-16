<?php

namespace App\Actions\Generations;

use App\Billing\Usd;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateGeneration
{
    public function __construct(private QuoteGeneration $quoteGeneration) {}

    /**
     * Quote the generation, reserve the most it could cost, and queue it for Pixelforge.
     *
     * @param  list<string>  $referenceImages
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $prompt, string $size, string $quality, array $referenceImages = []): Generation
    {
        $quote = $this->quoteGeneration->handle($prompt, $size, $quality, count($referenceImages));

        $generation = DB::transaction(function () use ($user, $prompt, $size, $quality, $referenceImages, $quote): Generation {
            // Locking the user makes their concurrent submissions check the balance one at a time.
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $available = $user->credits()['available'];

            if ($available < $quote->maxPriceNanos) {
                throw ValidationException::withMessages([
                    'credits' => sprintf(
                        'This could cost up to %s, but you have %s available. Buy credits to continue.',
                        Usd::format($quote->maxPriceNanos),
                        Usd::format($available),
                    ),
                ]);
            }

            return $user->generations()->create([
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'reference_images' => $referenceImages,
                'estimated_price_nanos' => $quote->estimatedPriceNanos,
                'max_price_nanos' => $quote->maxPriceNanos,
            ]);
        });

        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
