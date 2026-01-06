<?php

use Illuminate\Support\Facades\Route;
use Modules\Corsec\Http\Controllers\CorsecController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('corsecs', CorsecController::class)->names('corsec');
});
