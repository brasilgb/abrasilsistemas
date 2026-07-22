<?php

use App\Http\Controllers\Api\ProspectImportController;
use Illuminate\Support\Facades\Route;

Route::post('prospects/import', ProspectImportController::class)
    ->middleware('throttle:30,1')
    ->name('api.prospects.import');
