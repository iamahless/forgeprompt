<?php

use App\Http\Controllers\GenerationController;
use App\Http\Controllers\GenerationQuoteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [GenerationController::class, 'index'])->name('dashboard');
    Route::post('generations', [GenerationController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('generations.store');
    Route::post('generations/quote', GenerationQuoteController::class)->name('generations.quote');
});

require __DIR__.'/settings.php';
