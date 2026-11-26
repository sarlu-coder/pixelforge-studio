<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GenerationController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [GenerationController::class, 'index'])->name('studio.index');
    Route::get('/studio', [GenerationController::class, 'index'])->name('studio.index');
    Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
    Route::get('/generations/{generation}/sync', [GenerationController::class, 'sync'])->name('generations.sync');

    Route::post('/credits/top-up', [CreditController::class, 'topUp'])->name('credits.topup');

    Route::get('/admin/report', [AdminReportController::class, 'index'])->name('admin.report');
});

Route::post('/webhooks/pixelforge', [GenerationController::class, 'webhook'])->name('webhooks.pixelforge');

require __DIR__.'/auth.php';
