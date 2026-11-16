<?php

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\Rates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generation as its owner sees it: their price, never what the provider charged us.
 *
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images,
            'estimated_price_nanos' => $this->estimated_price_nanos,
            'max_price_nanos' => $this->max_price_nanos,
            'price_nanos' => $this->price_nanos,
            'usage' => $this->status === GenerationStatus::Succeeded ? $this->only(Rates::TOKEN_TYPES) : null,
            'image_url' => $this->image_url,
            'image_url_expires_at' => $this->image_url_expires_at,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
        ];
    }
}
