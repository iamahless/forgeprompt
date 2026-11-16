<?php

namespace App\Actions\Webhooks;

use App\Actions\Generations\ApplyProviderSnapshot;
use App\Models\WebhookEvent;
use App\Pixelforge\GenerationSnapshot;
use Illuminate\Support\Facades\DB;

class HandlePixelforgeEvent
{
    public function __construct(private ApplyProviderSnapshot $applySnapshot) {}

    /**
     * Record a Pixelforge event and apply it, unless this event id has been seen before.
     *
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): void
    {
        // The event and its effect commit together, so a crash can't leave an event recorded but not applied.
        DB::transaction(function () use ($event): void {
            // A single insert, so two copies arriving at the same moment can't both count as new.
            $isNew = WebhookEvent::query()->insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'payload' => json_encode($event, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            if ($isNew && str_starts_with($event['type'], 'generation.')) {
                $this->applySnapshot->handle(GenerationSnapshot::fromArray($event['data']['object']));
            }
        });
    }
}
