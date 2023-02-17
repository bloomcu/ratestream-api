<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use DDD\Http\Rates\RateController;
use DDD\Http\Rates\RateImportController;
use DDD\Http\Files\FileController;

Route::middleware('auth:sanctum')->group(function() {
    // Rates
    Route::prefix('{organization:slug}/rates')->group(function() {
        Route::get('/', [RateController::class, 'index']);
        Route::post('/', [RateController::class, 'store']);
        Route::get('/{rate}', [RateController::class, 'show']);
        Route::put('/{rate}', [RateController::class, 'update']);
        Route::delete('/{rate}', [RateController::class, 'destroy']);
    });

    // Rates Import
    Route::prefix('{organization:slug}/rates/import')->group(function() {
        Route::post('/', [RateImportController::class, 'import']);
    });

    // Files
    Route::prefix('{organization:slug}/files')->group(function() {
        Route::get('/', [FileController::class, 'index']);
        Route::post('/', [FileController::class, 'store']);
        Route::get('/{file}', [FileController::class, 'show']);
        Route::delete('/{file}', [FileController::class, 'destroy']);
    });
});
