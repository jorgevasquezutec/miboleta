<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DownloadUserBatchTemplateRequest;
use App\Http\Requests\StoreUserBatchRequest;
use App\Http\Requests\UploadUserBatchDataRequest;
use App\Http\Requests\ValidateUserBatchDataRequest;
use App\Http\Requests\ValidateUserBatchFileRequest;
use App\Jobs\ProcessUserChunk;
use App\Models\User;
use App\Services\ActiveTenantResolver;
use App\Models\UserBatch;
use App\Services\AuditService;
use App\Services\BulkUserUploadService;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class UserBatchController extends Controller
{
    public function __construct(
        private BulkUserUploadService $service,
        private AuditService $auditService,
        private ActiveTenantResolver $activeTenantResolver
    ) {
    }

    /**
     * FIX B2-r2: index/show/destroy/downloadErrors/getConfig solo estaban
     * protegidos por el middleware auth:sanctum de la ruta (ver
     * routes/api.php); cualquier usuario autenticado -incluido un
     * 'client'- podía listar/ver/cancelar/eliminar batches de carga
     * masiva. El global scope TenantFilterScope de UserBatch ya aísla por
     * EMPRESA, pero no por ROL dentro de la misma empresa. Se exige aquí
     * el mismo rol que ya exigen StoreUserBatchRequest/
     * UploadUserBatchDataRequest para iniciar una carga (root/admin/
     * admin_tenant).
     */
    private function authorizeBatchManager(): User
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            abort(403, 'No tienes permisos para gestionar cargas masivas.');
        }

        // El rol se resuelve dentro de la empresa ACTIVA, no con el respaldo
        // global: antes, ser admin en cualquiera de sus empresas bastaba para
        // gestionar cargas masivas de la empresa activa (fuga entre empresas).
        //
        // Se conserva a propósito el mismo conjunto de roles de hoy: este paso
        // solo cierra la fuga. La Matriz de Accesos ('users.bulk_upload' =
        // [root, admin_tenant], sin 'admin') se aplicará junto con el router y
        // el sidebar del frontend, para no dejar un menú visible que el backend
        // rechace con 403.
        $role = User::roleForTenant($user, $this->activeTenantResolver->resolve(request(), $user));

        if (!in_array($role, ['root', 'admin', 'admin_tenant'], true)) {
            abort(403, 'No tienes permisos para gestionar cargas masivas.');
        }

        return $user;
    }

    /**
     * FIX B2-r2: además del rol, para operaciones sobre UN batch puntual
     * (show/destroy/downloadErrors) exige que pertenezca al usuario
     * (created_by_user_id) o a un tenant que administre (admin/
     * admin_tenant). root queda exceptuado. Sin esto, un admin de la
     * Empresa A con acceso legítimo a la carga masiva podía ver/cancelar/
     * eliminar batches de la Empresa B con solo adivinar/enumerar el id.
     */
    private function authorizeBatchOwnership(UserBatch $batch, User $user): void
    {
        if ($user->isRoot()) {
            return;
        }

        if ($batch->created_by_user_id === $user->id) {
            return;
        }

        if (
            $batch->tenant_id
            && ($user->hasRoleInTenant('admin', $batch->tenant_id) || $user->hasRoleInTenant('admin_tenant', $batch->tenant_id))
        ) {
            return;
        }

        abort(403, 'No tienes permisos para acceder a este batch.');
    }

    /**
     * GET /api/user-batches/config
     * Obtener configuración para el modal de template
     */
    public function getConfig()
    {
        $this->authorizeBatchManager();

        $config = $this->service->getConfigData();

        return response()->json($config);
    }

    /**
     * POST /api/user-batches/template
     * Generar y descargar template Excel personalizado
     */
    public function downloadTemplate(DownloadUserBatchTemplateRequest $request)
    {
        $validated = $request->validated();

        return $this->service->generateTemplate(
            $validated['max_organizations'],
            $validated['organization_ids'] ?? null
        );
    }

    /**
     * GET /api/user-batches
     * Listar batches de carga masiva (historial)
     */
    public function index(Request $request)
    {
        $user = $this->authorizeBatchManager();
        $perPage = $request->get('per_page', 15);

        $batches = UserBatch::with(['tenant', 'createdBy'])
            ->when($request->has('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            // FIX B2-r2: root ve todo (TenantFilterScope ya no le aplica
            // restricción); un admin/admin_tenant solo ve los batches que
            // creó o los de un tenant que administra.
            ->when(!$user->isRoot(), function ($q) use ($user) {
                $managedTenantIds = $user->tenantRoles()
                    ->whereHas('role', fn ($r) => $r->whereIn('name', ['admin', 'admin_tenant']))
                    ->pluck('tenant_id');

                $q->where(function ($q2) use ($user, $managedTenantIds) {
                    $q2->where('created_by_user_id', $user->id)
                        ->orWhereIn('tenant_id', $managedTenantIds);
                });
            })
            ->orderBy('created_at', 'desc');

        // $sqlQuery = $batches->toSql();
        // $sqlBindings = $batches->getBindings();
        // for ($i = 0; $i < count($sqlBindings); $i++) {
        //     $value = is_numeric($sqlBindings[$i]) ? $sqlBindings[$i] : "'" . $sqlBindings[$i] . "'";
        //     $sqlQuery = preg_replace('/\?/', $value, $sqlQuery, 1);
        // }
        // Log::debug('[UserBatchController@index] SQL Query: ' . $sqlQuery);

        $batches = $batches->paginate($perPage);

        return response()->json([
            'data' => $batches->map(fn($b) => [
                'id' => $b->id,
                'uuid' => $b->uuid,
                'filename' => $b->original_filename,
                'file_size' => $b->file_size,
                'tenant' => $b->tenant ? [
                    'id' => $b->tenant->id,
                    'name' => $b->tenant->name,
                ] : null,
                'created_by' => $b->createdBy ? [
                    'id' => $b->createdBy->id,
                    'name' => $b->createdBy->full_name,
                ] : null,
                'status' => $b->status,
                'status_text' => $b->status_text,
                'status_badge' => $b->status_badge,
                'total_rows' => $b->total_rows,
                'processed_rows' => $b->processed_rows,
                'created_users' => $b->created_users,
                'updated_users' => $b->updated_users,
                'failed_rows' => $b->failed_rows,
                'progress_percentage' => $b->progress_percentage,
                'formatted_progress' => $b->formatted_progress,
                'duration' => $b->duration,
                'has_errors' => $b->hasErrors(),
                'created_at' => $b->created_at,
                'started_at' => $b->started_at,
                'completed_at' => $b->completed_at,
            ]),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
                'from' => $batches->firstItem(),
                'to' => $batches->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/user-batches/{id}
     * Ver detalle de un batch específico
     */
    public function show(int $id)
    {
        $user = $this->authorizeBatchManager();

        $batch = UserBatch::with(['tenant', 'createdBy'])
            ->findOrFail($id);

        $this->authorizeBatchOwnership($batch, $user);

        // Obtener progreso en tiempo real del Laravel Bus Batch si está disponible
        $batchProgress = $batch->batch_progress;

        return response()->json([
            'id' => $batch->id,
            'uuid' => $batch->uuid,
            'filename' => $batch->original_filename,
            'file_size' => $batch->file_size,
            'file_path' => $batch->file_path,
            'tenant' => $batch->tenant ? [
                'id' => $batch->tenant->id,
                'name' => $batch->tenant->name,
                'ruc' => $batch->tenant->ruc,
            ] : null,
            'created_by' => $batch->createdBy ? [
                'id' => $batch->createdBy->id,
                'name' => $batch->createdBy->full_name,
                'email' => $batch->createdBy->email,
            ] : null,
            'status' => $batch->status,
            'status_text' => $batch->status_text,
            'status_badge' => $batch->status_badge,
            'progress' => [
                'total_rows' => $batch->total_rows,
                'processed_rows' => $batch->processed_rows,
                'created_users' => $batch->created_users,
                'updated_users' => $batch->updated_users,
                'failed_rows' => $batch->failed_rows,
                'percentage' => $batch->progress_percentage,
                'formatted' => $batch->formatted_progress,
                'current_chunk' => $batch->current_chunk,
                'total_chunks' => $batch->total_chunks,
            ],
            'batch_progress' => $batchProgress, // ✅ Progreso en tiempo real de Laravel Bus
            'errors' => $batch->error_summary,
            'summary' => $batch->success_summary,
            // 'affected_tenant_ids' es bookkeeping interno (ver
            // UserBatch::mergeAffectedTenantIds), no se expone en la API.
            'processing_options' => collect($batch->processing_options)->except('affected_tenant_ids')->all(),
            'duration' => $batch->duration,
            'has_errors' => $batch->hasErrors(),
            'is_processing' => $batch->isProcessing(),
            'is_completed' => $batch->isCompleted(),
            'started_at' => $batch->started_at,
            'completed_at' => $batch->completed_at,
            'created_at' => $batch->created_at,
            'updated_at' => $batch->updated_at,
        ]);
    }

    /**
     * POST /api/user-batches/validate
     * Validar archivo y obtener preview de datos (sin procesar)
     */
    public function validate(ValidateUserBatchFileRequest $request)
    {
        $file = $request->file('file');

        // Validar y parsear archivo
        $validation = $this->service->validateFile($file);

        // Retornar preview con datos parseados, errores y warnings
        return response()->json([
            'valid' => $validation['valid'],
            'data' => $validation['data'] ?? [], // Usuarios a crear
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'summary' => $validation['summary'],
            'file_info' => [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ],
        ]);
    }

    /**
     * POST /api/user-batches
     * Iniciar carga masiva de usuarios (datos ya validados)
     */
    public function store(StoreUserBatchRequest $request)
    {
        $validated = $request->validated();
        $file = $request->file('file');

        // 1. Validar y parsear archivo
        $validation = $this->service->validateFile($file);

        if (!$validation['valid']) {
            return response()->json([
                'message' => 'El archivo contiene errores de validación',
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'summary' => $validation['summary'],
            ], 422);
        }

        // Si hay warnings pero no errores, permitir continuar
        if ($validation['summary']['errors'] > 0) {
            return response()->json([
                'message' => 'El archivo contiene errores que deben corregirse',
                'errors' => $validation['errors'],
                'summary' => $validation['summary'],
            ], 422);
        }

        // 2. Guardar archivo en storage
        $path = $file->store('user-batches', 'private');

        // 3. Obtener tenant_id (null para usuarios root)
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $tenantId = $user->tenants->first()?->id;

        // 4. Crear batch en BD
        $batch = UserBatch::create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $user->id,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'status' => 'pending',
            'total_rows' => count($validation['data']),
            'processing_options' => [
                'send_welcome_emails' => $validated['send_welcome_emails'] ?? false,
                // FIX M1: default false (comportamiento histórico: no
                // actualizar salvo que se pida explícitamente). Antes,
                // omitir el flag equivalía a update_existing=true, lo que
                // podía pisar silenciosamente datos de usuarios existentes
                // (incluido, antes del fix I3, el email/rol) sin que quien
                // sube el archivo lo haya pedido.
                'update_existing' => $validated['update_existing'] ?? false,
            ],
        ]);

        $this->auditService->logUserBatchCreated($batch->id, $batch->total_rows);

        // 5. Dividir usuarios en chunks y crear jobs
        $chunkSize = 50;
        $userChunks = array_chunk($validation['data'], $chunkSize);
        $jobs = [];

        foreach ($userChunks as $index => $chunk) {
            $jobs[] = new ProcessUserChunk($batch->uuid, $chunk, $index + 1);
        }

        // 6. Despachar batch de jobs con callbacks
        $laravelBatch = Bus::batch($jobs)
            ->name("Bulk User Upload: {$batch->original_filename}")
            ->then(fn(Batch $lb) => UserBatch::onBatchCompleted($batch->id))
            ->catch(fn(Batch $lb, \Throwable $e) => UserBatch::onBatchFailed($batch->id, $e->getMessage()))
            ->finally(fn(Batch $lb) => UserBatch::onBatchFinished($batch->id, $lb->failedJobs))
            ->onQueue('bulk-uploads')
            ->dispatch();

        // 7. Guardar batch_id en el registro
        $batch->update([
            'batch_id' => $laravelBatch->id,
            'status' => 'processing',
            'started_at' => now(),
            'total_chunks' => count($userChunks),
        ]);

        return response()->json([
            'message' => 'Carga masiva iniciada exitosamente',
            'batch' => [
                'id' => $batch->id,
                'uuid' => $batch->uuid,
                'total_rows' => $batch->total_rows,
                'status' => $batch->status,
                'status_text' => $batch->status_text,
            ],
            'warnings' => $validation['warnings'] ?? [],
        ], 201);
    }

    /**
     * POST /api/user-batches/validate-data
     * Validar datos editados (sin archivo) antes de procesar
     */
    public function validateData(ValidateUserBatchDataRequest $request)
    {
        $validated = $request->validated();

        $validation = $this->service->validateData($validated['users']);

        return response()->json([
            'valid' => $validation['valid'],
            'data' => $validation['data'],
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'summary' => $validation['summary'],
        ]);
    }

    /**
     * POST /api/user-batches/upload-data
     * Iniciar carga masiva con datos editados (sin archivo)
     */
    public function uploadData(UploadUserBatchDataRequest $request)
    {
        $validated = $request->validated();

        $users = $validated['users'];

        // 1. Obtener tenant_id (null para usuarios root)
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $tenantId = $user->tenants->first()?->id;

        // 2. Transformar organizaciones: ruc → tenant_id, supervisor_email → supervisor_id
        $users = $this->service->transformOrganizationsForProcessing($users);

        // 3. Crear batch en BD sin archivo
        $batch = UserBatch::create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $user->id,
            'original_filename' => 'datos_editados_' . now()->format('YmdHis') . '.json',
            'file_path' => null, // Sin archivo físico
            'file_size' => 0,
            'status' => 'pending',
            'total_rows' => count($users),
            'processing_options' => [
                'send_welcome_emails' => $validated['send_welcome_emails'] ?? false,
                // FIX M1: default false (comportamiento histórico: no
                // actualizar salvo que se pida explícitamente). Antes,
                // omitir el flag equivalía a update_existing=true, lo que
                // podía pisar silenciosamente datos de usuarios existentes
                // (incluido, antes del fix I3, el email/rol) sin que quien
                // sube el archivo lo haya pedido.
                'update_existing' => $validated['update_existing'] ?? false,
            ],
        ]);

        $this->auditService->logUserBatchCreated($batch->id, $batch->total_rows);

        // 4. Dividir usuarios en chunks y crear jobs
        $chunkSize = 50;
        $userChunks = array_chunk($users, $chunkSize);
        $jobs = [];

        foreach ($userChunks as $index => $chunk) {
            $jobs[] = new ProcessUserChunk($batch->uuid, $chunk, $index + 1);
        }

        // 5. Despachar batch de jobs con callbacks
        $laravelBatch = Bus::batch($jobs)
            ->name("Bulk User Upload (Edited): {$batch->original_filename}")
            ->then(fn(Batch $lb) => UserBatch::onBatchCompleted($batch->id))
            ->catch(fn(Batch $lb, \Throwable $e) => UserBatch::onBatchFailed($batch->id, $e->getMessage()))
            ->finally(fn(Batch $lb) => UserBatch::onBatchFinished($batch->id, $lb->failedJobs))
            ->onQueue('bulk-uploads')
            ->dispatch();

        // 6. Guardar batch_id en el registro
        $batch->update([
            'batch_id' => $laravelBatch->id,
            'status' => 'processing',
            'started_at' => now(),
            'total_chunks' => count($userChunks),
        ]);

        return response()->json([
            'message' => 'Carga masiva iniciada exitosamente',
            'batch' => [
                'id' => $batch->id,
                'uuid' => $batch->uuid,
                'total_rows' => $batch->total_rows,
                'status' => $batch->status,
                'status_text' => $batch->status_text,
            ],
            'warnings' => [],
        ], 201);
    }

    /**
     * GET /api/user-batches/{id}/errors
     * Descargar archivo Excel con errores del batch
     */
    public function downloadErrors(int $id)
    {
        $user = $this->authorizeBatchManager();

        $batch = UserBatch::findOrFail($id);

        $this->authorizeBatchOwnership($batch, $user);

        if (!$batch->hasErrors()) {
            return response()->json([
                'message' => 'Este batch no tiene errores para descargar',
            ], 404);
        }

        // TODO: Implementar export de errores
        // Por ahora retornamos los errores en JSON

        return response()->json([
            'message' => 'Error export not yet implemented',
            'errors' => $batch->error_summary,
        ], 501);
    }

    /**
     * DELETE /api/user-batches/{id}
     * Cancelar/eliminar un batch
     */
    public function destroy(int $id)
    {
        $user = $this->authorizeBatchManager();

        $batch = UserBatch::findOrFail($id);

        $this->authorizeBatchOwnership($batch, $user);

        // Si está en procesamiento, intentar cancelar el batch
        if ($batch->isProcessing()) {
            $cancelled = $batch->cancelBatch();

            if ($cancelled) {
                return response()->json([
                    'message' => 'Batch cancelado exitosamente',
                ]);
            }

            return response()->json([
                'message' => 'No se pudo cancelar el batch',
            ], 409); // Conflict
        }

        // Eliminar archivo si existe
        if ($batch->file_path && Storage::disk('private')->exists($batch->file_path)) {
            Storage::disk('private')->delete($batch->file_path);
        }

        $batch->delete();

        return response()->json([
            'message' => 'Batch eliminado exitosamente',
        ]);
    }
}
