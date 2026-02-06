<?php

use Illuminate\Support\Facades\Route;
use Modules\Corsec\Http\Controllers\DashboardController;
use Modules\Corsec\Http\Controllers\LetterController;
use Modules\Corsec\Http\Controllers\IncomingLetterController;
use Modules\Corsec\Http\Controllers\OutgoingLetterController;
use Modules\Corsec\Http\Controllers\MeetingController;
use Modules\Corsec\Http\Controllers\WorkplanController;
use Modules\Corsec\Http\Controllers\ApproverController;
use Modules\Corsec\Http\Controllers\DirectorateController;
use Modules\Corsec\Http\Controllers\SenderController;
use Modules\Corsec\Http\Controllers\LetterTypeController;
use Modules\Corsec\Http\Controllers\BankController;

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
            Route::get('/lookup-user', [IncomingLetterController::class, 'lookupUserByNik'])->middleware('permission:corsec.update')->name('lookup-user');
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
            Route::post('/{incomingLetter}/monitoring', [IncomingLetterController::class, 'addMonitoringDirectorates'])->middleware('permission:corsec.update')->name('monitoring.add');
            Route::post('/{incomingLetter}/verify', [IncomingLetterController::class, 'verifyAction'])->middleware('permission:corsec.authorize')->name('verify.action');
            Route::post('/{incomingLetter}/note', [IncomingLetterController::class, 'directorNote'])->middleware('permission:corsec.update')->name('director.note');
            // DELETE (pakai model binding biar ga tabrakan)
            Route::delete('/{incomingLetter}', [IncomingLetterController::class, 'destroy'])->middleware('permission:corsec.delete')->name('destroy');
        });
        Route::get('/outgoing', [LetterController::class, 'outgoing'])->name('outgoing.index');
    });

    Route::prefix('letter/outgoing')->name('letter.outgoing.')->group(function () {
        Route::get('/', [OutgoingLetterController::class, 'index'])->middleware('permission:corsec.read')->name('index');
        Route::get('/datatables', [OutgoingLetterController::class, 'datatables'])->middleware('permission:corsec.read')->name('datatables');
        Route::get('/create', [OutgoingLetterController::class, 'create'])->middleware('permission:corsec.create')->name('create');
        Route::post('/', [OutgoingLetterController::class, 'store'])->middleware('permission:corsec.create')->name('store');
        Route::get('/{outgoingLetter}', [OutgoingLetterController::class, 'show'])->middleware('permission:corsec.read')->name('show');
        Route::get('/{outgoingLetter}/edit', [OutgoingLetterController::class, 'edit'])->middleware('permission:corsec.update')->name('edit');
        Route::put('/{outgoingLetter}', [OutgoingLetterController::class, 'update'])->middleware('permission:corsec.update')->name('update');
        Route::post('/{outgoingLetter}/submit', [OutgoingLetterController::class, 'submit'])->middleware('permission:corsec.update')->name('submit');
        Route::post('/{outgoingLetter}/approval', [OutgoingLetterController::class, 'approvalAction'])->middleware('permission:corsec.authorize')->name('approval.action');
        Route::post('/{outgoingLetter}/compliance-review', [OutgoingLetterController::class, 'complianceReview'])->middleware('permission:corsec.update')->name('compliance.review');
        Route::post('/{outgoingLetter}/numbering', [OutgoingLetterController::class, 'numbering'])->middleware('permission:corsec.update')->name('numbering');
        Route::post('/{outgoingLetter}/upload-final', [OutgoingLetterController::class, 'uploadFinal'])->middleware('permission:corsec.update')->name('upload_final');
        Route::post('/{outgoingLetter}/verify', [OutgoingLetterController::class, 'verifyAction'])->middleware('permission:corsec.authorize')->name('verify.action');
    });

    // Meeting Routes
    Route::prefix('meeting')->name('meeting.')->group(function () {
        Route::get('/', [MeetingController::class, 'index'])->name('index');
        Route::get('/create', [MeetingController::class, 'create'])->name('create');
        Route::post('/store', [MeetingController::class, 'store'])->name('store');
        Route::get('/{meeting}', [MeetingController::class, 'show'])->name('show');
        Route::get('/{meeting}/edit', [MeetingController::class, 'edit'])->name('edit');
        Route::put('/{meeting}', [MeetingController::class, 'update'])->name('update');
        Route::delete('/{meeting}', [MeetingController::class, 'destroy'])->name('destroy');
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

    // Bank Routes
    Route::prefix('bank')->name('bank.')->group(function () {
        Route::get('datatables', [BankController::class, 'dataForDatatables'])->name('datatables');
        Route::get('export', [BankController::class, 'export'])->name('export');
        Route::post('delete-multiple', [BankController::class, 'deleteMultiple'])->name('deleteMultiple');
    });
    Route::resource('bank', BankController::class, [
        'names' => [
            'index'   => 'bank.index',
            'show'    => 'bank.show',
            'create'  => 'bank.create',
            'store'   => 'bank.store',
            'edit'    => 'bank.edit',
            'update'  => 'bank.update',
            'destroy' => 'bank.destroy',
        ],
    ]);
});
