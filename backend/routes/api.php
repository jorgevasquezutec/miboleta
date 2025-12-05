<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Rutas protegidas con Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // File Uploads
    Route::post('/upload/tenant-logo', [FileUploadController::class, 'uploadTenantLogo']);
    Route::delete('/upload/file', [FileUploadController::class, 'deleteFile']);

    // Users - Resource routes (index, store, show, update, destroy)
    Route::apiResource('users', UserController::class);

    // Users - Custom routes
    Route::prefix('users/{id}')->group(function () {
        Route::get('/subordinates', [UserController::class, 'subordinates']);
        Route::put('/supervisor', [UserController::class, 'assignSupervisor']);
    });

    // Tenants - Resource routes (index, store, show, update, destroy)
    Route::apiResource('tenants', \App\Http\Controllers\Api\TenantController::class);

    // Tenants - Custom routes for user management
    Route::prefix('tenants/{id}')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\TenantController::class, 'users']);
        Route::post('/users', [\App\Http\Controllers\Api\TenantController::class, 'addUser']);
        Route::delete('/users/{userId}', [\App\Http\Controllers\Api\TenantController::class, 'removeUser']);
    });
});