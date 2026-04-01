<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\MessageController;

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
        Route::get('/{referenceNumber}/messages',           [MessageController::class, 'index']);
        Route::post('/{referenceNumber}/messages',          [MessageController::class, 'store']);
    });
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/reports',                          [AdminController::class, 'index']);
    Route::get('/reports/{referenceNumber}',        [AdminController::class, 'show']);
    Route::patch('/reports/{referenceNumber}/status', [AdminController::class, 'updateStatus']);
    Route::get('/reports/{referenceNumber}/messages',           [MessageController::class, 'adminIndex']);
    Route::post('/reports/{referenceNumber}/messages',          [MessageController::class, 'adminStore']);
});
