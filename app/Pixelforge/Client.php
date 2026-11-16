<?php

namespace App\Pixelforge;

use App\Models\Generation;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Client
{
    public function pricing(): Pricing
    {
        // Only quotes read this. A finished generation is billed at the rates on its own record.
        return new Pricing(Cache::remember('pixelforge.pricing', now()->addMinutes(5), fn (): array => $this->send('get', 'pricing')));
    }

    /**
     * Submit a generation. Its id doubles as the idempotency key, so a retry can never create or bill a second one.
     */
    public function createGeneration(Generation $generation): GenerationSnapshot
    {
        return GenerationSnapshot::fromArray($this->send('post', 'generations', array_filter([
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'reference_images' => $generation->reference_images,
            'metadata' => ['generation_id' => $generation->id],
        ]), ['Idempotency-Key' => $generation->id]));
    }

    public function generation(string $providerId): GenerationSnapshot
    {
        return GenerationSnapshot::fromArray($this->send('get', "generations/{$providerId}"));
    }

    /**
     * What Pixelforge billed this API key per UTC day.
     *
     * @return array<string, mixed>
     */
    public function usage(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->send('get', 'usage', [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     *
     * @throws PixelforgeException
     */
    private function send(string $method, string $path, array $data = [], array $headers = []): array
    {
        try {
            $response = Http::baseUrl(config('services.pixelforge.base_url'))
                ->withToken(config('services.pixelforge.key'))
                ->withHeaders($headers)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->{$method}($path, $data);
        } catch (ConnectionException $exception) {
            throw new PixelforgeException('connection_error', 'Could not reach Pixelforge.', previous: $exception);
        }

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $response->json();
    }
}
