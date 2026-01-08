<?php

use Illuminate\Support\Facades\Route;
use Modules\Corsec\Http\Controllers\DashboardController;
use Modules\Corsec\Http\Controllers\LetterController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Letters Routes
    Route::prefix('letter')->name('letter.')->group(function () {
        Route::get('/', [LetterController::class, 'index'])->name('index');
        Route::get('/incoming', [LetterController::class, 'incoming'])->name('incoming.index');
        Route::get('/outgoing', [LetterController::class, 'outgoing'])->name('outgoing.index');
    });
    // Route::resource('letter', LetterController::class);
});
