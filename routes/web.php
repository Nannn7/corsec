<?php

use Illuminate\Support\Facades\Route;
use Modules\Corsec\Http\Controllers\DashboardController;
use Modules\Corsec\Http\Controllers\LetterController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('letter', LetterController::class);
});
