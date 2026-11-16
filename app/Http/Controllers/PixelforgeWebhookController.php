<?php

namespace App\Http\Controllers;

use App\Actions\Webhooks\HandlePixelforgeEvent;
use App\Http\Requests\PixelforgeWebhookRequest;
use Illuminate\Http\Response;

class PixelforgeWebhookController extends Controller
{
    /**
     * Receive a signed Pixelforge event.
     */
    public function __invoke(PixelforgeWebhookRequest $request, HandlePixelforgeEvent $handleEvent): Response
    {
        $handleEvent->handle($request->validated());

        return response()->noContent();
    }
}
