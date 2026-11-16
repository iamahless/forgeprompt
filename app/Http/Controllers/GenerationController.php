<?php

namespace App\Http\Controllers;

use App\Actions\Generations\CreateGeneration;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    /**
     * Show the generate form and the user's history.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'generations' => GenerationResource::collection(
                $user->generations()->latest()->orderByDesc('id')->paginate(12),
            ),
            'credits' => $user->credits(),
            'creditPacks' => config('billing.credit_packs'),
            'options' => [
                'sizes' => Generation::SIZES,
                'qualities' => Generation::QUALITIES,
                'maxPromptLength' => Generation::MAX_PROMPT_LENGTH,
                'maxReferenceImages' => Generation::MAX_REFERENCE_IMAGES,
            ],
        ]);
    }

    /**
     * Queue a new generation.
     */
    public function store(StoreGenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        $createGeneration->handle(
            $request->user(),
            $request->validated('prompt'),
            $request->validated('size'),
            $request->validated('quality'),
            $request->validated('reference_images', []),
        );

        return to_route('dashboard');
    }
}
