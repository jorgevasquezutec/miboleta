<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserBatch;
use App\Services\UserService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessUserChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutos por chunk
    public $tries = 1; // Sin reintentos - falla inmediatamente si hay error

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $batchUuid,
        private array $users,
        private int $chunkNumber
    ) {
        // Queue específica para bulk uploads
        $this->onQueue('bulk-uploads');
    }

    /**
     * Execute the job.
     */
    public function handle(UserService $userService): void
    {
        // ✅ Verificar si el batch fue cancelado
        if ($this->batch()->cancelled()) {
            Log::info("⏸️  [ProcessUserChunk] Batch cancelled, skipping chunk", [
                'batch_uuid' => $this->batchUuid,
                'chunk' => $this->chunkNumber,
            ]);
            return;
        }

        Log::info("🔄 [ProcessUserChunk] Processing chunk", [
            'batch_uuid' => $this->batchUuid,
            'chunk' => $this->chunkNumber,
            'users_count' => count($this->users),
        ]);

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($this->users as $userData) {
                try {
                    // Verificar si usuario existe
                    $existingUser = User::where('email', $userData['email'])->first();

                    if ($existingUser) {
                        // TODO: Implementar actualización de usuarios existentes
                        // Por ahora, omitir usuarios que ya existen
                        continue;
                    }

                    // Preparar datos en el formato que espera UserService
                    $userDataForService = [
                        'name' => $userData['nombre'],
                        'last_name' => $userData['apellido'],
                        'email' => $userData['email'],
                        'document_type' => $userData['tipo_documento'],
                        'document_text' => $userData['numero_documento'],
                        'phone' => $userData['telefono'] ?? null,
                        'status' => $userData['estado'],
                        'role_id' => $this->getRoleId($userData['rol']),
                        'tenants_config' => $this->formatTenantsConfig($userData['organizaciones']),
                    ];

                    // Crear usuario y despachar email con delay incremental (2s entre cada email)
                    $emailDelay = $created * 2; // 0s, 2s, 4s, 6s...
                    $userService->createUser($userDataForService, null, true, $emailDelay);
                    $created++;

                } catch (\Exception $e) {
                    Log::error('Error creating user in chunk', [
                        'batch_uuid' => $this->batchUuid,
                        'chunk' => $this->chunkNumber,
                        'email' => $userData['email'],
                        'error' => $e->getMessage(),
                    ]);

                    $errors[] = [
                        'nombre' => $userData['nombre'] ?? 'N/A',
                        'apellido' => $userData['apellido'] ?? 'N/A',
                        'email' => $userData['email'],
                        'documento' => $userData['numero_documento'] ?? 'N/A',
                        'rol' => $userData['rol'] ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            // 📊 Actualizar contadores en user_batches
            $this->updateBatchCounters($created, $updated, count($errors));

            // 💾 Guardar errores detallados
            if (!empty($errors)) {
                $this->saveErrors($errors);
            }

            // ⚠️ Si TODOS los usuarios fallaron, es un problema sistemático → fallar el job
            $totalUsers = count($this->users);
            if (count($errors) === $totalUsers && $totalUsers > 0) {
                $errorMessage = "All users failed to process. First error: " . ($errors[0]['error'] ?? 'Unknown');
                Log::error("❌ [ProcessUserChunk] All users failed", [
                    'batch_uuid' => $this->batchUuid,
                    'chunk' => $this->chunkNumber,
                    'total_users' => $totalUsers,
                    'first_error' => $errors[0]['error'] ?? 'Unknown',
                ]);
                throw new \Exception($errorMessage);
            }

            Log::info("✅ [ProcessUserChunk] Chunk completed", [
                'batch_uuid' => $this->batchUuid,
                'chunk' => $this->chunkNumber,
                'created' => $created,
                'updated' => $updated,
                'errors' => count($errors),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("❌ [ProcessUserChunk] Chunk failed", [
                'batch_uuid' => $this->batchUuid,
                'chunk' => $this->chunkNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw para reintentos automáticos
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [ProcessUserChunk] Chunk failed permanently", [
            'batch_uuid' => $this->batchUuid,
            'chunk' => $this->chunkNumber,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * Obtener ID del rol por nombre
     */
    private function getRoleId(string $roleName): int
    {
        $roleMap = [
            'root' => 1,
            'admin' => 2,
            'client' => 3,
        ];

        return $roleMap[strtolower($roleName)] ?? 3; // Default: client
    }

    /**
     * Formatear configuración de tenants
     * Para rol root: puede no tener organizaciones
     * Para rol client/admin: debe tener organizaciones con tenant_id
     */
    private function formatTenantsConfig(?array $organizaciones): array
    {
        // Si no hay organizaciones, retornar array vacío (válido para root)
        if (empty($organizaciones)) {
            return [];
        }

        $config = [];

        foreach ($organizaciones as $org) {
            // Saltar organizaciones vacías o sin tenant_id
            if (empty($org) || !isset($org['tenant_id'])) {
                continue;
            }
            
            $tenantId = (int) $org['tenant_id'];
            $supervisors = [];

            if (isset($org['supervisor_id'])) {
                $supervisors = [(int) $org['supervisor_id']];
            }

            $config[] = [
                'tenant_id' => $tenantId,
                'supervisors' => $supervisors,
            ];
        }

        return $config;
    }

    /**
     * Actualiza los contadores de user_batches después de procesar el chunk
     */
    protected function updateBatchCounters(int $created, int $updated, int $failed): void
    {
        try {
            UserBatch::where('uuid', $this->batchUuid)->increment('created_users', $created);
            UserBatch::where('uuid', $this->batchUuid)->increment('failed_rows', $failed);
            UserBatch::where('uuid', $this->batchUuid)->increment('processed_rows', $created);
            UserBatch::where('uuid', $this->batchUuid)->update(['current_chunk' => $this->chunkNumber]);
        } catch (\Exception $e) {
            Log::error('[ProcessUserChunk] Failed to update batch counters', [
                'batch_uuid' => $this->batchUuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Guarda los errores detallados en error_summary
     */
    protected function saveErrors(array $errors): void
    {
        if (empty($errors)) {
            return;
        }

        try {
            $batch = UserBatch::where('uuid', $this->batchUuid)->first();
            if (!$batch) {
                return;
            }

            // Obtener errores existentes o inicializar array vacío
            $existingErrors = $batch->error_summary ?? [];
            
            // Agregar nuevos errores con timestamp
            foreach ($errors as $error) {
                $existingErrors[] = array_merge($error, [
                    'chunk' => $this->chunkNumber,
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }

            $batch->update(['error_summary' => $existingErrors]);
        } catch (\Exception $e) {
            Log::error('[ProcessUserChunk] Failed to save errors', [
                'batch_uuid' => $this->batchUuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
