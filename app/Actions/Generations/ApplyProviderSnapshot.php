<?php

namespace App\Actions\Generations;

use App\Billing\Markup;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\GenerationSnapshot;
use App\Pixelforge\Rates;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplyProviderSnapshot
{
    /**
     * Apply Pixelforge's view of a generation. It only moves forward, so a late or repeated snapshot can't charge twice.
     */
    public function handle(GenerationSnapshot $snapshot): ?Generation
    {
        return DB::transaction(function () use ($snapshot): ?Generation {
            $generation = $this->lockGeneration($snapshot);

            if ($generation === null) {
                return null;
            }

            $generation->provider_id ??= $snapshot->id;
            $generation->rates ??= $snapshot->rates;
            $generation->started_at ??= $snapshot->startedAt;

            if ($snapshot->status->isAheadOf($generation->status)) {
                match ($snapshot->status) {
                    GenerationStatus::Succeeded => $this->recordSuccess($generation, $snapshot),
                    GenerationStatus::Failed => $generation->markFailed(
                        $snapshot->errorCode ?? 'generation_failed',
                        $snapshot->errorMessage ?? 'The generation failed.',
                        $snapshot->completedAt,
                    ),
                    default => $generation->status = $snapshot->status,
                };
            }

            $generation->save();

            return $generation;
        });
    }

    private function lockGeneration(GenerationSnapshot $snapshot): ?Generation
    {
        // A webhook can beat our own create request, before we know the provider id.
        return Generation::query()
            ->where('provider_id', $snapshot->id)
            ->when(Str::isUuid($snapshot->ourId), fn ($query) => $query->orWhere('id', $snapshot->ourId))
            ->lockForUpdate()
            ->first();
    }

    private function recordSuccess(Generation $generation, GenerationSnapshot $snapshot): void
    {
        $providerCost = $snapshot->cost();

        $generation->forceFill([
            ...Arr::only($snapshot->usage ?? [], Rates::TOKEN_TYPES),
            'status' => GenerationStatus::Succeeded,
            'provider_cost_nanos' => $providerCost,
            'price_nanos' => Markup::apply($providerCost),
            'image_url' => $snapshot->imageUrl,
            'image_url_expires_at' => $snapshot->imageUrlExpiresAt,
            'completed_at' => $snapshot->completedAt,
        ]);
    }
}
