<?php

use Illuminate\Support\Facades\Route;
use Modules\Corsec\Http\Controllers\DashboardController;
use Modules\Corsec\Http\Controllers\LetterController;
use Modules\Corsec\Http\Controllers\IncomingLetterController;
use Modules\Corsec\Http\Controllers\MeetingController;
use Modules\Corsec\Http\Controllers\WorkplanController;
use Modules\Corsec\Http\Controllers\ApproverController;
use Modules\Corsec\Http\Controllers\DirectorateController;
use Modules\Corsec\Http\Controllers\SenderController;
use Modules\Corsec\Http\Controllers\LetterTypeController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Letters Routes
    Route::prefix('letter')->name('letter.')->group(function () {
        Route::get('/', [LetterController::class, 'index'])->name('index');
        // Incoming Letter
        Route::prefix('incoming')->name('incoming.')->group(function () {
            // LIST
            Route::get('/', [IncomingLetterController::class, 'index'])->middleware('permission:corsec.read')->name('index');
            // STATIC ENDPOINTS (HARUS DI ATAS route param)
            Route::get('/datatables', [IncomingLetterController::class, 'datatables'])->middleware('permission:corsec.read')->name('datatables');
            Route::get('/export', [IncomingLetterController::class, 'export'])->middleware('permission:corsec.export')->name('export');
            Route::post('/delete-multiple', [IncomingLetterController::class, 'deleteMultiple'])->middleware('permission:corsec.delete')->name('delete_multiple');
            // CREATE
            Route::get('/create', [IncomingLetterController::class, 'create'])->middleware('permission:corsec.create')->name('create');
            Route::post('/', [IncomingLetterController::class, 'store'])->middleware('permission:corsec.create')->name('store');
            // DYNAMIC ROUTES
            Route::get('/{incomingLetter}', [IncomingLetterController::class, 'show'])->middleware('permission:corsec.read')->name('show');
            Route::get('/{incomingLetter}/edit', [IncomingLetterController::class, 'edit'])->middleware('permission:corsec.update')->name('edit');
            Route::put('/{incomingLetter}', [IncomingLetterController::class, 'update'])->middleware('permission:corsec.update')->name('update');
            // ACTIONS
            Route::post('/{incomingLetter}/submit', [IncomingLetterController::class, 'submit'])->middleware('permission:corsec.authorize')->name('submit');
            Route::post('/{incomingLetter}/circulate', [IncomingLetterController::class, 'circulate'])->middleware('permission:corsec.update')->name('circulate');
            Route::post('/{incomingLetter}/approval', [IncomingLetterController::class, 'approvalAction'])->middleware('permission:corsec.authorize')->name('approval.action');
            Route::post('/{incomingLetter}/directorate-update', [IncomingLetterController::class, 'directorateUpdate'])->middleware('permission:corsec.update')->name('directorate.update');
            Route::post('/{incomingLetter}/verify', [IncomingLetterController::class, 'verifyAction'])->middleware('permission:corsec.authorize')->name('verify.action');
            Route::post('/{incomingLetter}/note', [IncomingLetterController::class, 'directorNote'])->middleware('permission:corsec.update')->name('director.note');
            // DELETE (pakai model binding biar ga tabrakan)
            Route::delete('/{incomingLetter}', [IncomingLetterController::class, 'destroy'])->middleware('permission:corsec.delete')->name('destroy');
        });
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
    Route::middleware('auth')->prefix('approval')->name('approval.')->group(function () {
        Route::get('/', [ApproverController::class, 'index'])->middleware('permission:corsec.authorize')->name('index');
        Route::get('/datatables', [ApproverController::class, 'datatables'])->middleware('permission:corsec.authorize')->name('datatables');
        Route::get('/{approvalRequest}', [ApproverController::class, 'show'])->middleware('permission:corsec.authorize')->name('show');
        Route::post('/{approvalRequest}/approve', [ApproverController::class, 'approve'])
            ->middleware('permission:corsec.authorize')
            ->name('approve');
        Route::post('/{approvalRequest}/reject', [ApproverController::class, 'reject'])
            ->middleware('permission:corsec.authorize')
            ->name('reject');
    });

    // Directorate Routes
    Route::prefix('directorate')->name('directorate.')->group(function () {
        Route::get('datatables', [DirectorateController::class, 'dataForDatatables'])->name('datatables');
        Route::get('export', [DirectorateController::class, 'export'])->name('export');
        Route::post('delete-multiple', [DirectorateController::class, 'deleteMultiple'])->name('deleteMultiple');
    });
    Route::resource('directorate', DirectorateController::class, [
        'names' => [
            'index'   => 'directorate.index',
            'show'    => 'directorate.show',
            'create'  => 'directorate.create',
            'store'   => 'directorate.store',
            'edit'    => 'directorate.edit',
            'update'  => 'directorate.update',
            'destroy' => 'directorate.destroy',
        ],
    ]);

    // Sender Routes
    Route::prefix('sender')->name('sender.')->group(function () {
        Route::get('datatables', [SenderController::class, 'dataForDatatables'])->name('datatables');
        Route::get('export', [SenderController::class, 'export'])->name('export');
        Route::post('delete-multiple', [SenderController::class, 'deleteMultiple'])->name('deleteMultiple');
    });
    Route::resource('sender', SenderController::class, [
        'names' => [
            'index'   => 'sender.index',
            'show'    => 'sender.show',
            'create'  => 'sender.create',
            'store'   => 'sender.store',
            'edit'    => 'sender.edit',
            'update'  => 'sender.update',
            'destroy' => 'sender.destroy',
        ],
    ]);

    // Letter Type Routes
    Route::prefix('letter-type')->name('letter-type.')->group(function () {
        Route::get('datatables', [LetterTypeController::class, 'dataForDatatables'])->name('datatables');
        Route::get('export', [LetterTypeController::class, 'export'])->name('export');
        Route::post('delete-multiple', [LetterTypeController::class, 'deleteMultiple'])->name('deleteMultiple');
    });
    Route::resource('letter-type', LetterTypeController::class, [
        'names' => [
            'index'   => 'letter-type.index',
            'show'    => 'letter-type.show',
            'create'  => 'letter-type.create',
            'store'   => 'letter-type.store',
            'edit'    => 'letter-type.edit',
            'update'  => 'letter-type.update',
            'destroy' => 'letter-type.destroy',
        ],
    ]);
});
