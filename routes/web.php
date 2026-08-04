<?php

use Illuminate\Support\Facades\Route;
use Modules\Corsec\Http\Controllers\DashboardController;
use Modules\Corsec\Http\Controllers\LetterController;
use Modules\Corsec\Http\Controllers\IncomingLetterController;
use Modules\Corsec\Http\Controllers\OutgoingLetterController;
use Modules\Corsec\Http\Controllers\MeetingController;
use Modules\Corsec\Http\Controllers\ReportController;
use Modules\Corsec\Http\Controllers\LibraryController;
use Modules\Corsec\Http\Controllers\WorkplanController;
use Modules\Corsec\Http\Controllers\ApproverController;
use Modules\Corsec\Http\Controllers\DirectorateController;
use Modules\Corsec\Http\Controllers\SenderController;
use Modules\Corsec\Http\Controllers\LetterTypeController;
use Modules\Corsec\Http\Controllers\MeetingTypeController;
use Modules\Corsec\Http\Controllers\SecureStorageController;
use Modules\Corsec\Http\Middleware\LogCorsecRequestErrors;

$datatablesThrottle = 'throttle:corsec-datatables';
$previewThrottle = 'throttle:corsec-preview';
$writeHeavyThrottle = 'throttle:corsec-write-heavy';

Route::get('/storage/{path}', SecureStorageController::class)
    ->middleware('auth')
    ->where('path', '.*')
    ->name('storage.secure');

<<<<<<< HEAD
Route::middleware(['auth', 'permission:corsec.read|letter.read|meeting.read|workplan.read|report.read|library.read'])->group(function () {
=======
Route::middleware(['auth', 'permission:corsec.read'])->group(function () {
>>>>>>> 41a6d587a986009fad13830696d5399143b77ee3
    Route::get('/attachment/{attachment}/view', [SecureStorageController::class, 'viewAttachment'])->name('attachment.view');
    Route::get('/attachment/{attachment}/download', [SecureStorageController::class, 'downloadAttachment'])->name('attachment.download');
});

Route::middleware(['auth', LogCorsecRequestErrors::class])->group(function () use ($datatablesThrottle, $previewThrottle, $writeHeavyThrottle) {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Letters Routes
    Route::prefix('letter')->name('letter.')->group(function () use ($datatablesThrottle, $previewThrottle, $writeHeavyThrottle) {
        Route::get('/', [LetterController::class, 'index'])->name('index');
        // Incoming Letter
        Route::prefix('incoming')->name('incoming.')->group(function () use ($datatablesThrottle, $previewThrottle, $writeHeavyThrottle) {
            // LIST
            Route::get('/', [IncomingLetterController::class, 'index'])->middleware('permission:letter.read')->name('index');
            // STATIC ENDPOINTS (HARUS DI ATAS route param)
            Route::get('/datatables', [IncomingLetterController::class, 'datatables'])->middleware(['permission:letter.read', $datatablesThrottle])->name('datatables');
            Route::get('/export', [IncomingLetterController::class, 'export'])->middleware('permission:letter.export')->name('export');
            Route::post('/delete-multiple', [IncomingLetterController::class, 'deleteMultiple'])->middleware(['permission:letter.delete', $writeHeavyThrottle])->name('delete_multiple');
            Route::get('/lookup-user', [IncomingLetterController::class, 'lookupUserByNik'])->middleware(['permission:letter.update|letter.maker_action', $previewThrottle])->name('lookup-user');
            // CREATE
            Route::get('/create', [IncomingLetterController::class, 'create'])->middleware('permission:letter.create')->name('create');
            Route::post('/', [IncomingLetterController::class, 'store'])->middleware(['permission:letter.create', $writeHeavyThrottle])->name('store');
            // DYNAMIC ROUTES
            Route::get('/{incomingLetter}', [IncomingLetterController::class, 'show'])->middleware('permission:letter.read')->name('show');
            Route::get('/{incomingLetter}/edit', [IncomingLetterController::class, 'edit'])->middleware('permission:letter.update')->name('edit');
            Route::put('/{incomingLetter}', [IncomingLetterController::class, 'update'])->middleware(['permission:letter.update', $writeHeavyThrottle])->name('update');
            // ACTIONS
            Route::post('/{incomingLetter}/submit', [IncomingLetterController::class, 'submit'])->middleware(['permission:letter.create|letter.update', $writeHeavyThrottle])->name('submit');
            Route::post('/{incomingLetter}/circulate', [IncomingLetterController::class, 'circulate'])->middleware(['permission:letter.update', $writeHeavyThrottle])->name('circulate');
            Route::post('/{incomingLetter}/approval', [IncomingLetterController::class, 'approvalAction'])->middleware(['permission:letter.authorize', $writeHeavyThrottle])->name('approval.action');
<<<<<<< HEAD
            Route::post('/{incomingLetter}/directorate-update', [IncomingLetterController::class, 'directorateUpdate'])->middleware(['permission:letter.update|letter.maker_action', $writeHeavyThrottle])->name('directorate.update');
=======
            Route::post('/{incomingLetter}/directorate-update', [IncomingLetterController::class, 'directorateUpdate'])->middleware(['permission:letter.read', $writeHeavyThrottle])->name('directorate.update');
>>>>>>> 7e84cce245b01817c83717d179fd74b0a8e5fcf2
            Route::post('/{incomingLetter}/monitoring', [IncomingLetterController::class, 'addMonitoringDirectorates'])->middleware(['permission:letter.update', $writeHeavyThrottle])->name('monitoring.add');
            Route::post('/{incomingLetter}/verify', [IncomingLetterController::class, 'verifyAction'])->middleware(['permission:letter.read|letter.authorize|letter.update', $writeHeavyThrottle])->name('verify.action');
            Route::post('/{incomingLetter}/note', [IncomingLetterController::class, 'directorNote'])->middleware(['permission:letter.read', $writeHeavyThrottle])->name('director.note');
            // DELETE (pakai model binding biar ga tabrakan)
            Route::delete('/{incomingLetter}', [IncomingLetterController::class, 'destroy'])->middleware(['permission:letter.delete', $writeHeavyThrottle])->name('destroy');
        });
        Route::get('/outgoing', [LetterController::class, 'outgoing'])->name('outgoing.index');
    });

    Route::prefix('letter/outgoing')->name('letter.outgoing.')->group(function () use ($datatablesThrottle, $previewThrottle, $writeHeavyThrottle) {
        Route::get('/', [OutgoingLetterController::class, 'index'])->middleware('permission:letter.read')->name('index');
        Route::get('/datatables', [OutgoingLetterController::class, 'datatables'])->middleware(['permission:letter.read', $datatablesThrottle])->name('datatables');
        Route::get('/export', [OutgoingLetterController::class, 'export'])->middleware('permission:letter.export')->name('export');
        Route::post('/delete-multiple', [OutgoingLetterController::class, 'deleteMultiple'])->middleware(['permission:letter.delete', $writeHeavyThrottle])->name('delete_multiple');
        Route::get('/registration-preview', [OutgoingLetterController::class, 'registrationPreview'])->middleware(['permission:letter.create', $previewThrottle])->name('registration_preview');
        Route::get('/incoming-preview', [OutgoingLetterController::class, 'incomingPreview'])->middleware(['permission:letter.create|letter.update', $previewThrottle])->name('incoming_preview');
        Route::get('/create', [OutgoingLetterController::class, 'create'])->middleware('permission:letter.create')->name('create');
        Route::post('/', [OutgoingLetterController::class, 'store'])->middleware(['permission:letter.create', $writeHeavyThrottle])->name('store');
        Route::get('/{outgoingLetter}', [OutgoingLetterController::class, 'show'])->middleware('permission:letter.read')->name('show');
        Route::get('/{outgoingLetter}/edit', [OutgoingLetterController::class, 'edit'])->middleware('permission:letter.update')->name('edit');
        Route::put('/{outgoingLetter}', [OutgoingLetterController::class, 'update'])->middleware(['permission:letter.update', $writeHeavyThrottle])->name('update');
        Route::post('/{outgoingLetter}/submit', [OutgoingLetterController::class, 'submit'])->middleware(['permission:letter.update', $writeHeavyThrottle])->name('submit');
        Route::post('/{outgoingLetter}/cancel-request', [OutgoingLetterController::class, 'cancelRequest'])->middleware(['permission:letter.create|letter.update', $writeHeavyThrottle])->name('cancel.request');
        Route::post('/{outgoingLetter}/cancel-approval', [OutgoingLetterController::class, 'cancelApproval'])->middleware(['permission:letter.authorize', $writeHeavyThrottle])->name('cancel.approval');
        Route::post('/{outgoingLetter}/approval', [OutgoingLetterController::class, 'approvalAction'])->middleware(['permission:letter.authorize', $writeHeavyThrottle])->name('approval.action');
        Route::post('/{outgoingLetter}/compliance-review', [OutgoingLetterController::class, 'complianceReview'])->middleware(['permission:letter.update', $writeHeavyThrottle])->name('compliance.review');
        Route::post('/{outgoingLetter}/upload-final', [OutgoingLetterController::class, 'uploadFinal'])->middleware(['permission:letter.create|letter.update', $writeHeavyThrottle])->name('upload_final');
        Route::post('/{outgoingLetter}/note', [OutgoingLetterController::class, 'directorNote'])->middleware(['permission:letter.read', $writeHeavyThrottle])->name('director.note');
        Route::delete('/{outgoingLetter}', [OutgoingLetterController::class, 'destroy'])->middleware(['permission:letter.delete', $writeHeavyThrottle])->name('destroy');
    });

    Route::prefix('report')->name('report.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->middleware('permission:report.read')->name('index');
    });

    Route::prefix('library')->name('library.')->group(function () use ($writeHeavyThrottle) {
        Route::get('/', [LibraryController::class, 'index'])->middleware('permission:library.read')->name('index');
        Route::get('/guideline', [LibraryController::class, 'guidelineIndex'])->middleware('permission:library.read')->name('guideline.index');
        Route::get('/create', [LibraryController::class, 'create'])->middleware('permission:library.create')->name('create');
        Route::post('/', [LibraryController::class, 'store'])->middleware(['permission:library.create', $writeHeavyThrottle])->name('store');
        Route::get('/{libraryItem}/download', [LibraryController::class, 'download'])->middleware('permission:library.read')->name('download');
        Route::get('/{libraryItem}/edit', [LibraryController::class, 'edit'])->middleware('permission:library.create')->name('edit');
        Route::put('/{libraryItem}', [LibraryController::class, 'update'])->middleware(['permission:library.create', $writeHeavyThrottle])->name('update');
        Route::delete('/{libraryItem}', [LibraryController::class, 'destroy'])->middleware(['permission:library.delete', $writeHeavyThrottle])->name('destroy');
    });

    // Meeting Routes
    Route::prefix('meeting')->name('meeting.')->group(function () use ($datatablesThrottle, $writeHeavyThrottle) {
        Route::get('/', [MeetingController::class, 'index'])->middleware('permission:meeting.read')->name('index');
        Route::get('/tabulation', [MeetingController::class, 'tabulation'])->middleware('permission:meeting.read')->name('tabulation');
        Route::get('/datatables', [MeetingController::class, 'datatables'])->middleware(['permission:meeting.read', $datatablesThrottle])->name('datatables');
        Route::get('/export', [MeetingController::class, 'export'])->middleware('permission:meeting.export')->name('export');
        Route::get('/create', [MeetingController::class, 'create'])->middleware('permission:meeting.create')->name('create');
        Route::post('/store', [MeetingController::class, 'store'])->middleware(['permission:meeting.create', $writeHeavyThrottle])->name('store');
        Route::get('/{meeting}', [MeetingController::class, 'show'])->middleware('permission:meeting.read')->name('show');
        Route::get('/{meeting}/persentation', [MeetingController::class, 'presentation'])->middleware('permission:meeting.read')->name('presentation');
        Route::get('/{meeting}/materials/{material}/file', [MeetingController::class, 'materialFile'])->middleware('permission:meeting.read')->name('material.file');
        Route::get('/{meeting}/edit', [MeetingController::class, 'edit'])->middleware('permission:meeting.update')->name('edit');
        Route::put('/{meeting}', [MeetingController::class, 'update'])->middleware(['permission:meeting.update', $writeHeavyThrottle])->name('update');
        Route::delete('/{meeting}', [MeetingController::class, 'destroy'])->middleware(['permission:meeting.delete', $writeHeavyThrottle])->name('destroy');
        Route::post('/{meeting}/submit', [MeetingController::class, 'submit'])->middleware(['permission:meeting.update', $writeHeavyThrottle])->name('submit');
        Route::post('/{meeting}/corsec-approval', [MeetingController::class, 'corsecApproval'])->middleware(['permission:meeting.authorize', $writeHeavyThrottle])->name('corsec.approval');
        Route::post('/{meeting}/directorate-response', [MeetingController::class, 'directorateResponse'])->middleware(['permission:meeting.update', $writeHeavyThrottle])->name('directorate.response');
        Route::post('/{meeting}/mark-pending-directorate', [MeetingController::class, 'markPendingDirectorate'])->middleware(['permission:meeting.update', $writeHeavyThrottle])->name('mark.pending.directorate');
        Route::post('/{meeting}/directorate-submit', [MeetingController::class, 'directorateSubmit'])->middleware(['permission:meeting.update', $writeHeavyThrottle])->name('directorate.submit');
        Route::post('/{meeting}/directorate-approval', [MeetingController::class, 'directorateApproval'])->middleware(['permission:meeting.authorize', $writeHeavyThrottle])->name('directorate.approval');
        Route::post('/{meeting}/note', [MeetingController::class, 'directorNote'])->middleware(['permission:meeting.read', $writeHeavyThrottle])->name('director.note');
        Route::post('/{meeting}/minutes', [MeetingController::class, 'saveMinutes'])->middleware(['permission:meeting.update', $writeHeavyThrottle])->name('minutes.save');
        Route::post('/{meeting}/minutes/finalize', [MeetingController::class, 'finalizeMinutes'])->middleware(['permission:meeting.update', $writeHeavyThrottle])->name('minutes.finalize');
        Route::get('/{meeting}/minutes/template', [MeetingController::class, 'downloadDirectorateMinutesTemplate'])->middleware('permission:meeting.read')->name('minutes.template');
        Route::post('/{meeting}/decisions/{decision}/updates', [MeetingController::class, 'submitDecisionUpdate'])->middleware(['permission:meeting.read|meeting.update', $writeHeavyThrottle])->name('decision.update');
        Route::post('/{meeting}/followup/complete', [MeetingController::class, 'completeFollowup'])->middleware(['permission:meeting.update', $writeHeavyThrottle])->name('followup.complete');
    });

    // Workplan Routes
    Route::prefix('workplan')->name('workplan.')->group(function () use ($datatablesThrottle, $writeHeavyThrottle) {
        Route::get('/', [WorkplanController::class, 'index'])->middleware('permission:workplan.read')->name('index');
        Route::get('/datatables', [WorkplanController::class, 'datatables'])->middleware(['permission:workplan.read', $datatablesThrottle])->name('datatables');
        Route::get('/export', [WorkplanController::class, 'export'])->middleware('permission:workplan.export')->name('export');
        Route::get('/create', [WorkplanController::class, 'create'])->middleware('permission:workplan.create')->name('create');
        Route::post('/', [WorkplanController::class, 'store'])->middleware(['permission:workplan.create', $writeHeavyThrottle])->name('store');
        Route::post('/{workplan}/submit', [WorkplanController::class, 'submit'])->middleware(['permission:workplan.update', $writeHeavyThrottle])->name('submit');
        Route::post('/{workplan}/approval', [WorkplanController::class, 'approvalAction'])->middleware(['permission:workplan.authorize', $writeHeavyThrottle])->name('approval.action');
        Route::post('/{workplan}/items/{item}/progress', [WorkplanController::class, 'submitProgress'])->middleware(['permission:workplan.update', $writeHeavyThrottle])->name('items.progress');
        Route::post('/{workplan}/note', [WorkplanController::class, 'directorNote'])->middleware(['permission:workplan.read', $writeHeavyThrottle])->name('director.note');
        Route::get('/{workplan}', [WorkplanController::class, 'show'])->middleware('permission:workplan.read')->name('show');
        Route::get('/{workplan}/edit', [WorkplanController::class, 'edit'])->middleware('permission:workplan.update')->name('edit');
        Route::put('/{workplan}', [WorkplanController::class, 'update'])->middleware(['permission:workplan.update', $writeHeavyThrottle])->name('update');
        Route::delete('/{workplan}', [WorkplanController::class, 'destroy'])->middleware(['permission:workplan.delete', $writeHeavyThrottle])->name('destroy');
    });

    // Approver Routes
    Route::middleware('auth')->prefix('approval')->name('approval.')->group(function () use ($datatablesThrottle, $writeHeavyThrottle) {
        Route::get('/', [ApproverController::class, 'index'])->middleware('permission:corsec.read|corsec.authorize')->name('index');
        Route::get('/datatables', [ApproverController::class, 'datatables'])->middleware(['permission:corsec.read|corsec.authorize', $datatablesThrottle])->name('datatables');
        Route::get('/{approvalRequest}', [ApproverController::class, 'show'])->middleware('permission:corsec.read|corsec.authorize')->name('show');
        Route::post('/{approvalRequest}/approve', [ApproverController::class, 'approve'])
            ->middleware(['permission:corsec.authorize', $writeHeavyThrottle])
            ->name('approve');
        Route::post('/{approvalRequest}/reject', [ApproverController::class, 'reject'])
            ->middleware(['permission:corsec.authorize', $writeHeavyThrottle])
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

    // Meeting Type Routes
    Route::prefix('meeting-type')->name('meeting-type.')->group(function () {
        Route::get('datatables', [MeetingTypeController::class, 'dataForDatatables'])->name('datatables');
        Route::get('export', [MeetingTypeController::class, 'export'])->name('export');
        Route::post('delete-multiple', [MeetingTypeController::class, 'deleteMultiple'])->name('deleteMultiple');
    });
    Route::resource('meeting-type', MeetingTypeController::class, [
        'names' => [
            'index'   => 'meeting-type.index',
            'show'    => 'meeting-type.show',
            'create'  => 'meeting-type.create',
            'store'   => 'meeting-type.store',
            'edit'    => 'meeting-type.edit',
            'update'  => 'meeting-type.update',
            'destroy' => 'meeting-type.destroy',
        ],
        'parameters' => [
            'meeting-type' => 'meetingType',
        ],
    ]);

    // Letter Type In Routes
    Route::group([
        'prefix' => 'letter-type-in',
        'as' => 'letter-type.in.',
        'defaults' => ['scope' => 'in'],
    ], function () {
        Route::get('/', [LetterTypeController::class, 'index'])->name('index');
        Route::get('/datatables', [LetterTypeController::class, 'dataForDatatables'])->name('datatables');
        Route::get('/export', [LetterTypeController::class, 'export'])->name('export');
        Route::post('/delete-multiple', [LetterTypeController::class, 'deleteMultiple'])->name('deleteMultiple');
        Route::get('/create', [LetterTypeController::class, 'create'])->name('create');
        Route::post('/', [LetterTypeController::class, 'store'])->name('store');
        Route::get('/{letterType}', [LetterTypeController::class, 'show'])->name('show');
        Route::get('/{letterType}/edit', [LetterTypeController::class, 'edit'])->name('edit');
        Route::put('/{letterType}', [LetterTypeController::class, 'update'])->name('update');
        Route::delete('/{letterType}', [LetterTypeController::class, 'destroy'])->name('destroy');
    });

    // Letter Type Out Routes
    Route::group([
        'prefix' => 'letter-type-out',
        'as' => 'letter-type.out.',
        'defaults' => ['scope' => 'out'],
    ], function () {
        Route::get('/', [LetterTypeController::class, 'index'])->name('index');
        Route::get('/datatables', [LetterTypeController::class, 'dataForDatatables'])->name('datatables');
        Route::get('/export', [LetterTypeController::class, 'export'])->name('export');
        Route::post('/delete-multiple', [LetterTypeController::class, 'deleteMultiple'])->name('deleteMultiple');
        Route::get('/create', [LetterTypeController::class, 'create'])->name('create');
        Route::post('/', [LetterTypeController::class, 'store'])->name('store');
        Route::get('/{letterType}', [LetterTypeController::class, 'show'])->name('show');
        Route::get('/{letterType}/edit', [LetterTypeController::class, 'edit'])->name('edit');
        Route::put('/{letterType}', [LetterTypeController::class, 'update'])->name('update');
        Route::delete('/{letterType}', [LetterTypeController::class, 'destroy'])->name('destroy');
    });

    // Legacy Letter Type Routes (default to In)
    Route::group([
        'prefix' => 'letter-type',
        'as' => 'letter-type.',
        'defaults' => ['scope' => 'in'],
    ], function () {
        Route::get('/', [LetterTypeController::class, 'index'])->name('index');
        Route::get('/datatables', [LetterTypeController::class, 'dataForDatatables'])->name('datatables');
        Route::get('/export', [LetterTypeController::class, 'export'])->name('export');
        Route::post('/delete-multiple', [LetterTypeController::class, 'deleteMultiple'])->name('deleteMultiple');
        Route::get('/create', [LetterTypeController::class, 'create'])->name('create');
        Route::post('/', [LetterTypeController::class, 'store'])->name('store');
        Route::get('/{letterType}', [LetterTypeController::class, 'show'])->name('show');
        Route::get('/{letterType}/edit', [LetterTypeController::class, 'edit'])->name('edit');
        Route::put('/{letterType}', [LetterTypeController::class, 'update'])->name('update');
        Route::delete('/{letterType}', [LetterTypeController::class, 'destroy'])->name('destroy');
    });
});
