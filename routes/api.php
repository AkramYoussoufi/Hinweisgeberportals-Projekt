<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\SuperAdminSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register',        [AuthController::class, 'register']);
    Route::post('/login',           [AuthController::class, 'login']);
    Route::post('/anonymous-login', [AuthController::class, 'anonymousLogin']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

Route::prefix('reports')->middleware('auth:sanctum')->group(function () {
    Route::post('/',                                         [ReportController::class, 'store'])->withoutMiddleware('auth:sanctum')->middleware('throttle:report-submission');
    Route::get('/',                                          [ReportController::class, 'index']);
    Route::get('/{referenceNumber}',                         [ReportController::class, 'show']);
    Route::get('/{referenceNumber}/messages',                [MessageController::class, 'index']);
    Route::post('/{referenceNumber}/messages',               [MessageController::class, 'store']);
    Route::get('/{referenceNumber}/attachments',             [AttachmentController::class, 'index']);
    Route::post('/{referenceNumber}/attachments',            [AttachmentController::class, 'store']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/attachments/{attachmentId}/download',       [AttachmentController::class, 'download']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/reports',                                   [AdminController::class, 'index']);
    Route::get('/reports/{referenceNumber}',                 [AdminController::class, 'show']);
    Route::patch('/reports/{referenceNumber}/status',        [AdminController::class, 'updateStatus']);
    Route::get('/reports/{referenceNumber}/messages',        [MessageController::class, 'adminIndex']);
    Route::post('/reports/{referenceNumber}/messages',       [MessageController::class, 'adminStore']);
    Route::get('/reports/{referenceNumber}/attachments',     [AttachmentController::class, 'adminIndex']);
});

Route::prefix('superadmin')->middleware(['auth:sanctum', 'superadmin'])->group(function () {
    Route::get('/admins',                                    [SuperAdminController::class, 'listAdmins']);
    Route::post('/admins',                                   [SuperAdminController::class, 'createAdmin']);
    Route::patch('/admins/{adminId}/deactivate',             [SuperAdminController::class, 'deactivateAdmin']);
    Route::patch('/admins/{adminId}/reactivate',             [SuperAdminController::class, 'reactivateAdmin']);
    Route::delete('/admins/{adminId}',                       [SuperAdminController::class, 'deleteAdmin']);
    Route::patch('/admins/{adminId}/password',               [SuperAdminController::class, 'changeAdminPassword']);
    Route::get('/reports/{referenceNumber}/unlock-identity', [SuperAdminController::class, 'unlockIdentity']);
    Route::get('/settings',  [SuperAdminSettingsController::class, 'index']);
    Route::patch('/settings', [SuperAdminSettingsController::class, 'update']);
});

use Illuminate\Foundation\Auth\EmailVerificationRequest;


Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $frontendUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/');

    $user = \App\Models\User::find($id);

    if (!$user || !hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return redirect($frontendUrl . '/verify-email.html?status=error');
    }

    if ($user->hasVerifiedEmail()) {
        return redirect($frontendUrl . '/verify-email.html?status=already');
    }

    $user->markEmailAsVerified();

    return redirect($frontendUrl . '/verify-email.html?status=success');
})->middleware('signed')->name('verification.verify');

Route::post('/email/resend', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return response()->json(['message' => 'Verification email sent']);
})->middleware(['auth:sanctum', 'throttle:6,1']);
