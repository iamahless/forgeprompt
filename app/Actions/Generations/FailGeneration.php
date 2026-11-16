<?php

namespace App\Actions\Generations;

use App\Models\Generation;
use Illuminate\Support\Facades\DB;

class FailGeneration
{
    /**
     * Fail a generation we could not hand to Pixelforge, unless it has already finished.
     */
    public function handle(Generation $generation, string $code, string $message): void
    {
        DB::transaction(function () use ($generation, $code, $message): void {
            $generation = Generation::query()->lockForUpdate()->find($generation->id);

            if ($generation === null || $generation->status->isFinal()) {
                return;
            }

            $generation->markFailed($code, $message);
            $generation->save();
        });
    }
}
