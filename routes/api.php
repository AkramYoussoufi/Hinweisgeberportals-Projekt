<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register',        [AuthController::class, 'register']);
    Route::post('/login',           [AuthController::class, 'login']);
    Route::post('/anonymous-login', [AuthController::class, 'anonymousLogin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

Route::prefix('reports')->group(function () {
    Route::post('/', [ReportController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/',                        [ReportController::class, 'index']);
        Route::get('/{referenceNumber}',       [ReportController::class, 'show']);
    });
});
