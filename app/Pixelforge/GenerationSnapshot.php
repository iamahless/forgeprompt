<?php

namespace App\Pixelforge;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;

/**
 * The generation object as Pixelforge returns it, from the API and inside webhooks, at one moment in time.
 */
final readonly class GenerationSnapshot
{
    /**
     * @param  array<string, string>  $rates
     * @param  array<string, int>|null  $usage
     */
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public ?string $ourId,
        public array $rates,
        public ?array $usage,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $generation
     */
    public static function fromArray(array $generation): self
    {
        return new self(
            id: $generation['id'],
            status: GenerationStatus::from($generation['status']),
            ourId: $generation['metadata']['generation_id'] ?? null,
            rates: $generation['rates'],
            usage: $generation['usage'],
            imageUrl: $generation['image_url'],
            imageUrlExpiresAt: self::date($generation['image_url_expires_at']),
            errorCode: $generation['error']['code'] ?? null,
            errorMessage: $generation['error']['message'] ?? null,
            startedAt: self::date($generation['started_at']),
            completedAt: self::date($generation['completed_at']),
        );
    }

    /**
     * What Pixelforge charges for this generation in nanodollars. It never returns a price, only usage and rates.
     */
    public function cost(): int
    {
        return Rates::fromArray($this->rates)->cost($this->usage ?? []);
    }

    private static function date(?string $iso): ?CarbonImmutable
    {
        return $iso === null ? null : CarbonImmutable::parse($iso);
    }
}
