<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentBatchController;
use App\Http\Controllers\Api\DocumentSignatureController;
use App\Http\Controllers\Api\VacationRequestController;
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

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);

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

    // ============ MÓDULO 4: DOCUMENTOS ============

    // Document Types (solo lectura)
    Route::get('/document-types', [DocumentTypeController::class, 'index']);
    Route::get('/document-types/{id}', [DocumentTypeController::class, 'show']);

    // Documents
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/orphans', [DocumentController::class, 'orphans']);
    Route::get('/documents/{id}', [DocumentController::class, 'show']);
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{id}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::post('/documents/{id}/assign', [DocumentController::class, 'assignOrphan']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

    // Document Batches (Cargas masivas)
    Route::get('/document-batches', [DocumentBatchController::class, 'index']);
    Route::get('/document-batches/{id}', [DocumentBatchController::class, 'show']);
    Route::post('/document-batches/upload', [DocumentBatchController::class, 'upload']);
    Route::post('/document-batches/preview', [DocumentBatchController::class, 'previewZip']);

    // Document Signature (Firma digital con 2FA)
    Route::get('/signature/terms', [DocumentSignatureController::class, 'checkTerms']);
    Route::post('/signature/terms/accept', [DocumentSignatureController::class, 'acceptTerms']);
    Route::get('/documents/{id}/signature-status', [DocumentSignatureController::class, 'status']);
    Route::post('/documents/{id}/request-code', [DocumentSignatureController::class, 'requestCode']);
    Route::post('/documents/{id}/sign', [DocumentSignatureController::class, 'verifyAndSign']);

    // ============ MÓDULO 5: VACACIONES ============

    // Vacation Requests - Rutas especiales primero (antes de {id})
    Route::get('/vacation-requests/pending-approval', [VacationRequestController::class, 'pendingApprovals']);
    Route::get('/vacation-requests/pending-confirmation', [VacationRequestController::class, 'pendingConfirmations']);
    Route::get('/vacation-requests/my-team', [VacationRequestController::class, 'myTeam']);
    Route::get('/vacation-requests/my-decisions', [VacationRequestController::class, 'myDecisions']);

    // Vacation Requests - CRUD
    Route::get('/vacation-requests', [VacationRequestController::class, 'index']);
    Route::post('/vacation-requests', [VacationRequestController::class, 'store']);
    Route::get('/vacation-requests/{id}', [VacationRequestController::class, 'show']);
    Route::delete('/vacation-requests/{id}', [VacationRequestController::class, 'destroy']);

    // Vacation Requests - Acciones de supervisor
    Route::put('/vacation-requests/{id}/approve', [VacationRequestController::class, 'approve']);
    Route::put('/vacation-requests/{id}/reject', [VacationRequestController::class, 'reject']);
    Route::put('/vacation-requests/{id}/mark-taken', [VacationRequestController::class, 'markTaken']);
    Route::put('/vacation-requests/{id}/mark-not-taken', [VacationRequestController::class, 'markNotTaken']);
});