<?php

namespace App\Pixelforge;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class PixelforgeException extends RuntimeException
{
    private const array RETRYABLE_CODES = ['rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded', 'connection_error'];

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 0,
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $retryAfter = $response->header('Retry-After');

        return new self(
            $response->json('error.code') ?? 'http_'.$response->status(),
            $response->json('error.message') ?? 'Pixelforge returned an unexpected response.',
            $response->status(),
            is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }

    /**
     * Whether the same request can succeed later. A used-up daily quota is a 429 too, but waiting won't help.
     */
    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE_CODES, true) || $this->status >= 500;
    }
}
