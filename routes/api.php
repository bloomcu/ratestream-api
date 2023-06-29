<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use DDD\Http\Columns\ColumnController;
use DDD\Http\Columns\ColumnOrderController;
use DDD\Http\CSV\CSVController;
use DDD\Http\Rates\RateController;
use DDD\Http\Rates\RateBatchController;
use DDD\Http\Rates\RateImportController;

// Rates - Public
Route::prefix('{organization:slug}/rates')->group(function() {
    Route::get('/', [RateController::class, 'index']);
    Route::get('/{rate}', [RateController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function() {
    // Columns
    Route::prefix('{organization:slug}/columns')->group(function() {
        Route::post('/', [ColumnController::class, 'store']);
    });

    // Column order
    Route::prefix('{organization:slug}/columns')->group(function() {
        Route::put('/{column}/order', [ColumnOrderController::class, 'update']);
    });
    
    // CSVs
    Route::prefix('{organization:slug}/csv')->group(function() {
        Route::get('/{file}', [CSVController::class, 'show']);
    });

    // Rates
    Route::prefix('{organization:slug}/rates')->group(function() {
        // Route::get('/', [RateController::class, 'index']);
        Route::post('/', [RateController::class, 'store']);
        // Route::get('/{rate:uid}', [RateController::class, 'show']);
        Route::put('/{rate:uid}', [RateController::class, 'update']);
        Route::delete('/{rate:uid}', [RateController::class, 'destroy']);
    });

    // Rates batch
    Route::prefix('{organization:slug}/rates/batch')->group(function() {
        Route::post('/', [RateBatchController::class, 'handle']);
    });

    // Rates Import
    Route::prefix('{organization:slug}/rates/import')->group(function() {
        Route::post('/', [RateImportController::class, 'import']);
    });
});
