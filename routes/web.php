<?php

use Illuminate\Support\Facades\Route;
use Modules\Corsec\Http\Controllers\CorsecController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('corsecs', CorsecController::class)->names('corsec');
});
