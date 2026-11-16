<?php

namespace App\Jobs;

use App\Actions\Generations\ApplyProviderSnapshot;
use App\Actions\Generations\FailGeneration;
use App\Models\Generation;
use App\Pixelforge\Client;
use App\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /**
     * Seconds to wait before each retry, unless Pixelforge's Retry-After asks for longer. After the last one we give up.
     */
    private const array RETRY_DELAYS = [2, 5, 10, 20, 30, 60, 60];

    private const string BUSY_MESSAGE = 'Pixelforge is too busy right now. You were not charged. Please try again in a few minutes.';

    public int $tries = 8;

    public int $backoff = 10;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Generation $generation) {}

    public function handle(Client $pixelforge, ApplyProviderSnapshot $applySnapshot, FailGeneration $failGeneration): void
    {
        if ($this->generation->provider_id !== null || $this->generation->status->isFinal()) {
            return;
        }

        try {
            $snapshot = $pixelforge->createGeneration($this->generation);
        } catch (PixelforgeException $exception) {
            $delay = self::RETRY_DELAYS[$this->attempts() - 1] ?? null;

            if ($exception->isRetryable() && $delay !== null) {
                $this->release(max($exception->retryAfter ?? 0, $delay));

                return;
            }

            Log::warning('Pixelforge did not accept a generation.', [
                'generation_id' => $this->generation->id,
                'code' => $exception->errorCode,
                'message' => $exception->getMessage(),
            ]);

            $failGeneration->handle($this->generation, $exception->errorCode, $this->messageFor($exception));

            return;
        }

        $applySnapshot->handle($snapshot);
    }

    /**
     * The job itself kept crashing or timing out, so the generation must not be left queued forever.
     */
    public function failed(?Throwable $exception): void
    {
        app(FailGeneration::class)->handle($this->generation, 'submission_failed', self::BUSY_MESSAGE);
    }

    private function messageFor(PixelforgeException $exception): string
    {
        return match ($exception->errorCode) {
            'content_policy_violation' => 'Your prompt was rejected by the content policy. You were not charged.',
            'daily_limit_exceeded' => 'Today\'s generation limit has been reached. You were not charged. Please try again tomorrow.',
            'rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded', 'connection_error' => self::BUSY_MESSAGE,
            default => 'We could not start this generation. You were not charged.',
        };
    }
}
