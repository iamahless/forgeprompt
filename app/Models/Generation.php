<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Carbon\CarbonInterface;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prices are what the user pays; the provider cost is what Pixelforge charges us. Both are in nanodollars.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string|null $provider_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property list<string> $reference_images
 * @property int $estimated_price_nanos
 * @property int $max_price_nanos
 * @property array<string, string>|null $rates
 * @property int|null $input_text_tokens
 * @property int|null $input_image_tokens
 * @property int|null $output_tokens
 * @property int|null $reasoning_tokens
 * @property int|null $provider_cost_nanos
 * @property int|null $price_nanos
 * @property string|null $image_url
 * @property CarbonInterface|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['prompt', 'size', 'quality', 'reference_images', 'estimated_price_nanos', 'max_price_nanos'])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUuids;

    public const array SIZES = ['1024x1024', '1536x1024', '1024x1536'];

    public const array QUALITIES = ['low', 'medium', 'high'];

    public const int MAX_PROMPT_LENGTH = 4000;

    public const int MAX_REFERENCE_IMAGES = 4;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => GenerationStatus::Queued->value,
        'reference_images' => '[]',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'image_url_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generations that are still queued or processing.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInFlight(Builder $query): void
    {
        $query->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing]);
    }

    /**
     * Mark the generation as failed. A failed generation costs nothing.
     */
    public function markFailed(string $code, string $message, ?CarbonInterface $completedAt = null): void
    {
        $this->forceFill([
            'status' => GenerationStatus::Failed,
            'error_code' => $code,
            'error_message' => $message,
            'provider_cost_nanos' => 0,
            'price_nanos' => 0,
            'completed_at' => $completedAt ?? now(),
        ]);
    }
}
