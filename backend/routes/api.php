<?php

use App\Http\Controllers\Api\AuthController;
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

    // Users - Resource routes (index, store, show, update, destroy)
    Route::apiResource('users', UserController::class);

    // Users - Custom routes
    Route::prefix('users/{id}')->group(function () {
        Route::get('/subordinates', [UserController::class, 'subordinates']);
        Route::put('/supervisor', [UserController::class, 'assignSupervisor']);
    });
});
