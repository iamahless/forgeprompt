<?php

namespace App\Console\Commands;

use App\Actions\Generations\ApplyProviderSnapshot;
use App\Models\Generation;
use App\Pixelforge\Client;
use App\Pixelforge\PixelforgeException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('generations:reconcile')]
#[Description('Poll Pixelforge for generations whose webhooks are overdue')]
class ReconcileGenerations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Client $pixelforge, ApplyProviderSnapshot $applySnapshot): int
    {
        // Webhook delivery isn't guaranteed, so anything quiet for a minute is fetched instead.
        $overdue = Generation::query()
            ->inFlight()
            ->whereNotNull('provider_id')
            ->where('updated_at', '<', now()->subMinute())
            ->get();

        foreach ($overdue as $generation) {
            try {
                $applySnapshot->handle($pixelforge->generation($generation->provider_id));
            } catch (PixelforgeException $exception) {
                $this->warn("{$generation->provider_id}: {$exception->getMessage()}");
            }
        }

        $this->info("Checked {$overdue->count()} overdue generation(s).");

        return self::SUCCESS;
    }
}
