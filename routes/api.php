<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\TeamController;

// Routes publiques
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/auth/reset-password', [PasswordResetController::class, 'resetPassword']);

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/meetings', [AudioController::class, 'index']);
    Route::get('/meetings/search', [AudioController::class, 'search']);
    Route::get('/meetings/{meetingId}', [AudioController::class, 'show']);
    Route::post('/meetings/upload', [AudioController::class, 'upload']);
    Route::post('/auth/send-code', [TwoFactorController::class, 'sendCode']);
    Route::post('/auth/verify-code', [TwoFactorController::class, 'verifyCode']);
    Route::get('/team/members', [TeamController::class, 'index']);
    Route::post('/meetings/{meetingId}/invite', [TeamController::class, 'inviteParticipants']);
});