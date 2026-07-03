<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentBatchController;
use App\Http\Controllers\Api\DocumentSignatureController;
use App\Http\Controllers\Api\VacationRequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\UserBatchController;
use App\Http\Controllers\Api\SignatureSettingsController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health Check Routes (públicas, sin autenticación)
Route::prefix('health')->group(function () {
    Route::get('/ping', [HealthController::class, 'ping']);      // Simple ping
    Route::get('/check', [HealthController::class, 'check']);    // Detailed health
    Route::get('/ready', [HealthController::class, 'ready']);    // Kubernetes readiness
    Route::get('/live', [HealthController::class, 'live']);      // Kubernetes liveness
});

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Password recovery (públicas)
Route::post('/password/forgot', [PasswordController::class, 'forgotPassword']);
Route::post('/password/reset', [PasswordController::class, 'resetPassword']);

// Broadcasting auth (requires authentication)
Broadcast::routes(['middleware' => ['auth:sanctum']]);

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
    Route::post('/profile/request-data-update', [ProfileController::class, 'requestDataUpdate']);

    // Password management (autenticado)
    Route::post('/password/change', [PasswordController::class, 'changePassword']);
    Route::post('/password/force-change', [PasswordController::class, 'forceChangePassword']);

    // File Uploads
    Route::post('/upload/tenant-logo', [FileUploadController::class, 'uploadTenantLogo']);
    Route::delete('/upload/file', [FileUploadController::class, 'deleteFile']);

    // Roles - Catálogo de roles (para asignar por empresa en UserFormPage)
    Route::get('/roles', [RoleController::class, 'index']);

    // Users - Custom routes (BEFORE resource to avoid conflicts)
    Route::get('/users/subordinates', [UserController::class, 'subordinates']);
    Route::prefix('users/{id}')->group(function () {
        Route::get('/subordinates', [UserController::class, 'subordinates']);
        Route::post('/reset-password', [PasswordController::class, 'adminResetPassword']);
    });

    // Users - Resource routes (index, store, show, update, destroy)
    Route::apiResource('users', UserController::class);

    // ============ CARGA MASIVA DE USUARIOS ============

    // User Batches - Custom routes first
    Route::get('/user-batches/config', [UserBatchController::class, 'getConfig']);
    Route::post('/user-batches/template', [UserBatchController::class, 'downloadTemplate']);
    Route::post('/user-batches/validate', [UserBatchController::class, 'validate']);
    Route::post('/user-batches/validate-data', [UserBatchController::class, 'validateData']);
    Route::post('/user-batches/upload-data', [UserBatchController::class, 'uploadData']);
    Route::get('/user-batches/{id}/errors', [UserBatchController::class, 'downloadErrors']);

    // User Batches - REST routes
    Route::get('/user-batches', [UserBatchController::class, 'index']);
    Route::post('/user-batches', [UserBatchController::class, 'store']);
    Route::get('/user-batches/{id}', [UserBatchController::class, 'show']);
    Route::delete('/user-batches/{id}', [UserBatchController::class, 'destroy']);

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

    // Firma digital CRIPTOGRÁFICA (PAdES, certificado de plataforma - distinta del flujo 2FA de abajo)
    Route::post('/documents/{id}/sign-digital', [DocumentController::class, 'signDigital']);
    Route::get('/documents/{id}/verify-signature', [DocumentController::class, 'verifyDigital']);

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

    // Signature Settings (Certificado de firma digital de plataforma - solo root)
    Route::get('/signature/settings', [SignatureSettingsController::class, 'show']);
    Route::put('/signature/settings', [SignatureSettingsController::class, 'update']);
    Route::post('/signature/certificate', [SignatureSettingsController::class, 'store']);
    Route::delete('/signature/certificate', [SignatureSettingsController::class, 'destroy']);

    // ============ MÓDULO 5: VACACIONES ============

    // Vacation Requests - Rutas especiales primero (antes de {id})
    Route::get('/vacation-requests/balance', [VacationRequestController::class, 'balance']);
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

    // ============ MÓDULO 6: NOTIFICACIONES ============

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // ============ MÓDULO 7: REPORTES Y AUDITORÍA ============

    Route::prefix('reports')->group(function () {
        // Dashboard stats
        Route::get('/dashboard', [ReportsController::class, 'dashboard']);

        // Individual (per-user) stats for the employee dashboard
        Route::get('/my-stats', [ReportsController::class, 'myStats']);

        // Individual stats
        Route::get('/documents', [ReportsController::class, 'documents']);
        Route::get('/vacations', [ReportsController::class, 'vacations']);
        Route::get('/users', [ReportsController::class, 'users']);
        Route::get('/activity', [ReportsController::class, 'activity']);

        // Audit logs (admin/root only)
        Route::get('/audit', [ReportsController::class, 'audit']);
        Route::get('/audit/actions', [ReportsController::class, 'auditActions']);
        Route::get('/audit/export', [ReportsController::class, 'exportAudit']);

        // Exports
        Route::get('/documents/export', [ReportsController::class, 'exportDocuments']);
        Route::get('/vacations/export', [ReportsController::class, 'exportVacations']);
        Route::get('/users/export', [ReportsController::class, 'exportUsers']);
        Route::get('/batches/export', [ReportsController::class, 'exportBatches']);
        Route::get('/tenants/export', [ReportsController::class, 'exportTenants']);
    });
});