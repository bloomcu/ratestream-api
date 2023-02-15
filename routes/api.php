<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use DDD\Http\Rates\RateController;

Route::middleware('auth:sanctum')->group(function() {
    // Rates
    Route::prefix('{organization:slug}/rates')->group(function() {
        Route::get('/', [RateController::class, 'index']);
        Route::post('/', [RateController::class, 'store']);
        Route::get('/{rate}', [RateController::class, 'show']);
        Route::put('/{rate}', [RateController::class, 'update']);
        Route::delete('/{rate}', [RateController::class, 'destroy']);
    });
});
