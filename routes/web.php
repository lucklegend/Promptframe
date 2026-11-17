<?php

use App\Http\Controllers\GenerationController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('generate', [GenerationController::class, 'create'])->name('generate');
    Route::post('generate/quote', QuoteController::class)->name('generate.quote');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
