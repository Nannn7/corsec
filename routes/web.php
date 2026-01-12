<?php

use Illuminate\Support\Facades\Route;
use Modules\Corsec\Http\Controllers\DashboardController;
use Modules\Corsec\Http\Controllers\LetterController;
use Modules\Corsec\Http\Controllers\MeetingController;
use Modules\Corsec\Http\Controllers\WorkplanController;
use Modules\Corsec\Http\Controllers\ApproverController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Letters Routes
    Route::prefix('letter')->name('letter.')->group(function () {
        Route::get('/', [LetterController::class, 'index'])->name('index');
        Route::get('/incoming', [LetterController::class, 'incoming'])->name('incoming.index');
        Route::get('/outgoing', [LetterController::class, 'outgoing'])->name('outgoing.index');
    });

    // Meeting Routes
    Route::prefix('meeting')->name('meeting.')->group(function () {
        Route::get('/', [MeetingController::class, 'index'])->name('index');
        Route::get('/create', [MeetingController::class, 'create'])->name('create');
        Route::post('/store', [MeetingController::class, 'store'])->name('store');
        Route::get('/{id}', [MeetingController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [MeetingController::class, 'edit'])->name('edit');
        Route::put('/{id}', [MeetingController::class, 'update'])->name('update');
        Route::delete('/{id}', [MeetingController::class, 'destroy'])->name('destroy');
    });

    // Workplan Routes
    Route::prefix('workplan')->name('workplan.')->group(function () {
        Route::get('/', [WorkplanController::class, 'index'])->name('index');
    });

    // Approver Routes
    Route::prefix('approval')->name('approval.')->group(function () {
        Route::get('/', [ApproverController::class, 'index'])->name('index');
    });
});
