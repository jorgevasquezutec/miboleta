<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Password recovery (públicas)
Route::post('/password/forgot', [PasswordController::class, 'forgotPassword']);
Route::post('/password/reset', [PasswordController::class, 'resetPassword']);

// Rutas protegidas con Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Password management (autenticado)
    Route::post('/password/change', [PasswordController::class, 'changePassword']);
    Route::post('/password/force-change', [PasswordController::class, 'forceChangePassword']);

    // File Uploads
    Route::post('/upload/tenant-logo', [FileUploadController::class, 'uploadTenantLogo']);
    Route::delete('/upload/file', [FileUploadController::class, 'deleteFile']);

    // Users - Resource routes (index, store, show, update, destroy)
    Route::apiResource('users', UserController::class);

    // Users - Custom routes
    Route::prefix('users/{id}')->group(function () {
        Route::get('/subordinates', [UserController::class, 'subordinates']);
        Route::put('/supervisor', [UserController::class, 'assignSupervisor']);
        Route::post('/reset-password', [PasswordController::class, 'adminResetPassword']);
    });

    // Tenants - Resource routes (index, store, show, update, destroy)
    Route::apiResource('tenants', TenantController::class);

    // Tenants - Custom routes for user management
    Route::prefix('tenants/{id}')->group(function () {
        Route::get('/users', [TenantController::class, 'users']);
        Route::post('/users', [TenantController::class, 'addUser']);
        Route::delete('/users/{userId}', [TenantController::class, 'removeUser']);
    });
});