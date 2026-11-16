<?php

namespace App\Http\Controllers;

use App\Actions\Generations\QuoteGeneration;
use App\Http\Requests\QuoteGenerationRequest;
use Illuminate\Http\JsonResponse;

class GenerationQuoteController extends Controller
{
    /**
     * Price a generation the user is still writing, so the form can show the cost before they submit.
     */
    public function __invoke(QuoteGenerationRequest $request, QuoteGeneration $quoteGeneration): JsonResponse
    {
        $quote = $quoteGeneration->handle(
            $request->validated('prompt'),
            $request->validated('size'),
            $request->validated('quality'),
            $request->integer('reference_image_count'),
        );

        return response()->json([
            'estimated_price_nanos' => $quote->estimatedPriceNanos,
            'max_price_nanos' => $quote->maxPriceNanos,
        ]);
    }
}
