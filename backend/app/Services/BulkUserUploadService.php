<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Generator;
use Illuminate\Support\Facades\Auth;

class BulkUserUploadService
{
    // ────────────────────────────────────────────────────────────
    // CONFIGURACIÓN
    // ────────────────────────────────────────────────────────────

    /**
     * Obtener datos de configuración para el modal de template
     */
    public function getConfigData(): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Obtener organizaciones accesibles
        $organizations = $user->isRoot()
            ? Tenant::with('users')->where('status', 'active')->get()
            : $user->tenants;

        // Obtener supervisores por organización
        $supervisorsByOrg = [];
        foreach ($organizations as $org) {
            $supervisorsByOrg[$org->id] = User::whereHas('tenants', function ($q) use ($org) {
                $q->where('tenants.id', $org->id);
            })
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'admin');
                })
                ->select('id', 'email', 'name', 'last_name')
                ->get()
                ->map(fn($u) => [
                    'id' => $u->id,
                    'email' => $u->email,
                    'full_name' => $u->full_name,
                ]);
        }

        return [
            'organizations' => $organizations->map(fn($o) => [
                'id' => $o->id,
                'ruc' => $o->ruc,
                'name' => $o->name,
                'supervisors_count' => $supervisorsByOrg[$o->id]->count(),
            ]),
            'supervisors_by_org' => $supervisorsByOrg,
            'max_organizations_limit' => 3,
            'default_organizations' => 1,
        ];
    }

    // ────────────────────────────────────────────────────────────
    // GENERACIÓN DE TEMPLATE EXCEL
    // ────────────────────────────────────────────────────────────

    /**
     * Generar template Excel personalizado
     */
    public function generateTemplate(int $maxOrganizations, ?array $organizationIds = null): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $export = new \App\Exports\UsersTemplateExport($maxOrganizations, $organizationIds);

        $filename = sprintf(
            'template_usuarios_%dorgs_%s.xlsx',
            $maxOrganizations,
            now()->format('Ymd_His')
        );

        return Excel::download($export, $filename);
    }

    // ────────────────────────────────────────────────────────────
    // VALIDACIÓN DE ARCHIVO
    // ────────────────────────────────────────────────────────────

    /**
     * Validar archivo Excel y parsear datos
     */
    public function validateFile(UploadedFile $file): array
    {
        try {
            // Crear instancia del import
            $import = new \App\Imports\UsersImport();

            // Importar datos del Excel
            Excel::import($import, $file);

            // Obtener resultados
            $parsedData = $import->getParsedData();
            $errors = $import->getErrors();
            $warnings = $import->getWarnings();

            // Consolidar usuarios duplicados (mismo email)
            $consolidated = $this->consolidateDuplicates($parsedData);

            // Validar emails que ya existen en BD
            $duplicateEmails = $this->checkDuplicateEmails($consolidated);
            
            // Agregar errores de emails duplicados
            foreach ($duplicateEmails as $duplicate) {
                $errors[] = [
                    'row' => $duplicate['row'] ?? 0,
                    'field' => 'email',
                    'message' => "El email '{$duplicate['email']}' ya existe en el sistema para el usuario: {$duplicate['existing_user']}",
                ];
            }

            // Validar documentos que ya existen en BD
            $duplicateDocuments = $this->checkDuplicateDocuments($consolidated);
            
            // Agregar errores de documentos duplicados
            foreach ($duplicateDocuments as $duplicate) {
                $errors[] = [
                    'row' => $duplicate['row'] ?? 0,
                    'field' => 'numero_documento',
                    'message' => "El documento '{$duplicate['document']}' ya existe en el sistema para el usuario: {$duplicate['existing_user']}",
                ];
            }

            // Generar resumen
            $summary = [
                'total' => count($parsedData),
                'valid' => count($consolidated) - count($duplicateEmails) - count($duplicateDocuments),
                'errors' => count($errors),
                'warnings' => count($warnings),
                'consolidated_users' => count($parsedData) - count($consolidated),
                'duplicate_emails' => count($duplicateEmails),
                'duplicate_documents' => count($duplicateDocuments),
            ];

            // Determinar si es válido
            $isValid = count($errors) === 0 && count($consolidated) > 0;

            return [
                'valid' => $isValid,
                'data' => $consolidated,
                'original_data' => $parsedData,
                'errors' => $errors,
                'warnings' => $warnings,
                'summary' => $summary,
            ];

        } catch (\Exception $e) {
            Log::error('File validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'valid' => false,
                'data' => [],
                'errors' => [
                    [
                        'row' => 0,
                        'field' => 'file',
                        'message' => 'Error al procesar archivo: ' . $e->getMessage(),
                    ]
                ],
                'warnings' => [],
                'summary' => [
                    'total' => 0,
                    'valid' => 0,
                    'errors' => 1,
                    'warnings' => 0,
                ],
            ];
        }
    }

    // ────────────────────────────────────────────────────────────
    // CONSOLIDACIÓN DE DUPLICADOS
    // ────────────────────────────────────────────────────────────

    /**
     * Consolidar usuarios con email duplicado en el archivo
     */
    public function consolidateDuplicates(array $users): array
    {
        $consolidated = [];
        $emailMap = [];

        foreach ($users as $user) {
            $email = strtolower($user['email']);

            if (isset($emailMap[$email])) {
                // Usuario duplicado - consolidar organizaciones
                $existingIndex = $emailMap[$email];

                // Validar que datos básicos coincidan
                $this->validateDuplicateConsistency($consolidated[$existingIndex], $user);

                // Agregar organizaciones
                $consolidated[$existingIndex]['organizaciones'] = array_merge(
                    $consolidated[$existingIndex]['organizaciones'],
                    $user['organizaciones']
                );
            } else {
                // Nuevo usuario
                $emailMap[$email] = count($consolidated);
                $consolidated[] = $user;
            }
        }

        return $consolidated;
    }

    /**
     * Validar consistencia de datos en usuarios duplicados
     */
    private function validateDuplicateConsistency(array $existing, array $new): void
    {
        $fieldsToCheck = ['nombre', 'apellido', 'tipo_documento', 'numero_documento', 'rol', 'estado'];

        foreach ($fieldsToCheck as $field) {
            if ($existing[$field] !== $new[$field]) {
                throw new \Exception(
                    "Datos inconsistentes para email duplicado: {$existing['email']}. " .
                    "Campo '{$field}' no coincide."
                );
            }
        }
    }

    /**
     * Verificar si los emails ya existen en la base de datos
     */
    private function checkDuplicateEmails(array $users): array
    {
        $duplicates = [];
        
        // Extraer todos los emails
        $emails = array_filter(array_map(function($user) {
            return strtolower($user['email'] ?? '');
        }, $users));

        if (empty($emails)) {
            return [];
        }

        // Buscar emails existentes en BD
        $existingUsers = \App\Models\User::whereIn(\DB::raw('LOWER(email)'), $emails)
            ->select('id', 'name', 'last_name', 'email', 'document_text')
            ->get()
            ->keyBy(fn($user) => strtolower($user->email));

        // Verificar cada usuario del Excel
        foreach ($users as $index => $user) {
            $email = strtolower($user['email'] ?? '');
            
            if (!$email) {
                continue;
            }

            if ($existingUsers->has($email)) {
                $existingUser = $existingUsers->get($email);
                $duplicates[] = [
                    'row' => $index + 2, // +2 porque Excel empieza en 1 y tiene header
                    'email' => $user['email'],
                    'existing_user' => "{$existingUser->name} {$existingUser->last_name} ({$existingUser->email})",
                    'new_user' => "{$user['nombre']} {$user['apellido']} ({$user['email']})",
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Verificar si los documentos ya existen en la base de datos
     */
    private function checkDuplicateDocuments(array $users): array
    {
        $duplicates = [];
        
        // Extraer todos los números de documento
        $documents = array_filter(array_map(function($user) {
            return $user['numero_documento'] ?? null;
        }, $users));

        if (empty($documents)) {
            return [];
        }

        // Buscar documentos existentes en BD
        $existingUsers = \App\Models\User::whereIn('document_text', $documents)
            ->select('id', 'name', 'last_name', 'email', 'document_text')
            ->get()
            ->keyBy('document_text');

        // Verificar cada usuario del Excel
        foreach ($users as $index => $user) {
            $document = $user['numero_documento'] ?? null;
            
            if (!$document) {
                continue;
            }

            if ($existingUsers->has($document)) {
                $existingUser = $existingUsers->get($document);
                $duplicates[] = [
                    'row' => $index + 2, // +2 porque Excel empieza en 1 y tiene header
                    'document' => $document,
                    'existing_user' => "{$existingUser->name} {$existingUser->last_name} ({$existingUser->email})",
                    'new_user' => "{$user['nombre']} {$user['apellido']} ({$user['email']})",
                ];
            }
        }

        return $duplicates;
    }

    // ────────────────────────────────────────────────────────────
    // PROCESAMIENTO POR CHUNKS
    // ────────────────────────────────────────────────────────────

    /**
     * Procesar usuarios en chunks (Generator para streaming)
     */
    public function processUsersInChunks(array $users, int $chunkSize = 50): Generator
    {
        $chunks = array_chunk($users, $chunkSize);
        $totalChunks = count($chunks);
        $processed = 0;
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($chunks as $index => $chunk) {
            DB::beginTransaction();

            try {
                $result = $this->processChunk($chunk);
                DB::commit();

                $created += $result['created'];
                $updated += $result['updated'];
                $processed += count($chunk);

                yield [
                    'type' => 'progress',
                    'chunk' => $index + 1,
                    'total_chunks' => $totalChunks,
                    'processed' => $processed,
                    'total' => count($users),
                    'percentage' => round(($processed / count($users)) * 100, 1),
                    'created' => $created,
                    'updated' => $updated,
                    'failed' => count($errors),
                ];

                // Pequeña pausa para no saturar BD
                usleep(100000); // 0.1 seg

            } catch (\Exception $e) {
                DB::rollBack();

                $errors[] = [
                    'chunk' => $index + 1,
                    'message' => $e->getMessage(),
                ];

                Log::error('Bulk user upload chunk failed', [
                    'chunk' => $index + 1,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                yield [
                    'type' => 'error',
                    'chunk' => $index + 1,
                    'message' => $e->getMessage(),
                ];
            }
        }

        yield [
            'type' => 'complete',
            'summary' => [
                'total' => count($users),
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'failed_rows' => count($errors),
                'error_details' => $errors,
            ],
        ];
    }

    /**
     * Procesar un chunk de usuarios
     */
    private function processChunk(array $users): array
    {
        $created = 0;
        $updated = 0;
        $userService = app(\App\Services\UserService::class);

        foreach ($users as $userData) {
            // Verificar si usuario existe
            $existingUser = User::where('email', $userData['email'])->first();

            if ($existingUser) {
                // TODO: Implementar actualización de usuarios existentes
                // Por ahora, omitir usuarios que ya existen
                continue;
            }

            try {
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

                // Usar UserService para crear el usuario
                // Esto automáticamente:
                // - Genera password temporal aleatorio
                // - Envía email de bienvenida
                // - Asigna must_change_password = true
                // - Asigna roles y tenants con supervisores
                $user = $userService->createUser($userDataForService);

                $created++;
            } catch (\Exception $e) {
                Log::error('Error creating user in bulk upload', [
                    'email' => $userData['email'],
                    'error' => $e->getMessage(),
                ]);
                // Continuar con el siguiente usuario
                continue;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
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

        return $roleMap[$roleName] ?? 3; // Default: client
    }

    /**
     * Formatear organizaciones al formato que espera UserService
     */
    private function formatTenantsConfig(array $organizations): array
    {
        $tenantsConfig = [];

        foreach ($organizations as $index => $org) {
            $tenant = Tenant::where('ruc', $org['ruc'])->first();

            if (!$tenant) {
                continue;
            }

            $supervisorId = null;
            if (!empty($org['supervisor_email'])) {
                $supervisor = User::where('email', $org['supervisor_email'])->first();
                $supervisorId = $supervisor?->id;
            }

            $tenantsConfig[] = [
                'tenant_id' => $tenant->id,
                'is_primary' => $index === 0, // Primera organización es primaria
                'supervisor_id' => $supervisorId,
            ];
        }

        return $tenantsConfig;
    }

    /**
     * Asignar organizaciones y supervisores a un usuario
     * @deprecated Ya no se usa - UserService->createUser() maneja esto automáticamente
     */
    private function assignOrganizations(User $user, array $organizations): void
    {
        foreach ($organizations as $org) {
            $tenant = Tenant::where('ruc', $org['ruc'])->first();

            if (!$tenant) {
                continue;
            }

            // Buscar supervisor si está especificado
            $supervisorId = null;
            if (!empty($org['supervisor_email'])) {
                $supervisor = User::where('email', $org['supervisor_email'])->first();
                $supervisorId = $supervisor?->id;
            }

            // Sincronizar relación (attach si no existe, update si existe)
            $user->tenants()->syncWithoutDetaching([
                $tenant->id => [
                    'supervisor_id' => $supervisorId,
                    'is_primary' => false, // Se define después
                ]
            ]);
        }
    }

    // ────────────────────────────────────────────────────────────
    // HELPERS
    // ────────────────────────────────────────────────────────────

    /**
     * Generar password aleatorio seguro
     */
    private function generateSecurePassword(int $length = 12): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}
