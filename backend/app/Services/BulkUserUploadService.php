<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;

class BulkUserUploadService
{
    /**
     * Roles operativos asignables por organización (RP1-C) para un actor NO
     * root. 'root' queda excluido a propósito: es un rol global, no se
     * asigna por empresa.
     *
     * 'admin_tenant' quedó excluido desde [OBS-CLIENTE 2026-07] para TODOS
     * los actores; [OBS-CLIENTE 2026-08] aclaró que root sí debe poder
     * asignarlo por carga masiva (admin_tenant/admin siguen sin poder). Ver
     * allowedOrgRolesFor(), que resuelve la lista según quién sube el
     * archivo/grid.
     *
     * Fuente única: ValidationRulesSheet, InstructionsSheet, UsersImport,
     * UploadUserBatchDataRequest y ProcessUserChunk llaman a
     * allowedOrgRolesFor() en vez de mantener su propia copia de la lista.
     * Pública a propósito para que esas clases (otro namespace) puedan leerla.
     */
    public const ALLOWED_ORG_ROLES = ['admin', 'client', 'aprobador'];

    /**
     * Rol operativo por organización que solo un actor root puede asignar
     * por carga masiva [OBS-CLIENTE 2026-08]. Ver allowedOrgRolesFor().
     */
    private const ROOT_ONLY_ORG_ROLE = 'admin_tenant';

    /**
     * Lista de roles operativos por organización permitidos en la carga
     * masiva, según quién sube el archivo/grid. root puede asignar
     * 'admin_tenant' además de los roles base; cualquier otro actor
     * (incluido admin_tenant) solo puede asignar ALLOWED_ORG_ROLES.
     *
     * $actor null se trata como NO-root (fail-closed): los contextos en cola
     * (ProcessUserChunk) que no logran resolver al creador del batch deben
     * caer al conjunto más restrictivo, no al más permisivo.
     *
     * @return array<string>
     */
    public static function allowedOrgRolesFor(?User $actor): array
    {
        if ($actor && $actor->isRoot()) {
            return [...self::ALLOWED_ORG_ROLES, self::ROOT_ONLY_ORG_ROLE];
        }

        return self::ALLOWED_ORG_ROLES;
    }

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
            // Roles operativos asignables por organización (org{n}_rol en el
            // Excel / selector por empresa en el editor). Se expone desde BD
            // (sin hardcode) para que el frontend no tenga que duplicar la
            // lista. admin_tenant solo se incluye si quien pide la config es
            // root [OBS-CLIENTE 2026-08] (ver allowedOrgRolesFor).
            'available_roles' => Role::whereIn('name', self::allowedOrgRolesFor($user))
                ->select('id', 'name', 'display_name')
                ->get(),
        ];
    }

    // ────────────────────────────────────────────────────────────
    // TRANSFORMACIÓN DE DATOS PARA PROCESAMIENTO
    // ────────────────────────────────────────────────────────────

    /**
     * Transformar organizaciones de ruc/supervisor_email a tenant_id/supervisor_id
     * Esto es necesario porque el frontend envía RUC y el job necesita IDs
     */
    public function transformOrganizationsForProcessing(array $users): array
    {
        return array_map(function ($user) {
            // Si no hay organizaciones, retornar usuario sin cambios
            if (empty($user['organizaciones'])) {
                $user['organizaciones'] = [];
                return $user;
            }

            $transformedOrgs = [];
            foreach ($user['organizaciones'] as $org) {
                // Saltar orgs vacías
                if (empty($org['ruc'])) {
                    continue;
                }

                // Buscar tenant por RUC
                $tenant = Tenant::where('ruc', trim($org['ruc']))->first();
                if (!$tenant) {
                    continue; // Ya fue validado, pero por seguridad
                }

                $transformedOrg = [
                    'tenant_id' => $tenant->id,
                    'supervisor_id' => null,
                ];

                // Buscar supervisor si existe
                if (!empty($org['supervisor_email'])) {
                    $supervisor = User::where('email', trim($org['supervisor_email']))->first();
                    if ($supervisor) {
                        $transformedOrg['supervisor_id'] = $supervisor->id;
                    }
                }

                // Propagar rol(es)/fecha de ingreso/saldo de vacaciones por
                // empresa (RP1-C). Los campos ausentes se omiten; de los roles
                // faltantes se encarga la validación, que los exige.
                if (!empty($org['roles'])) {
                    $transformedOrg['roles'] = is_array($org['roles'])
                        ? array_values(array_filter(array_map('trim', $org['roles'])))
                        : array_values(array_filter(array_map('trim', explode(',', (string) $org['roles']))));
                }

                if (!empty($org['hire_date'])) {
                    $transformedOrg['hire_date'] = $org['hire_date'];
                }

                if (isset($org['vacation_balance_initial']) && $org['vacation_balance_initial'] !== null && $org['vacation_balance_initial'] !== '') {
                    $transformedOrg['vacation_balance_initial'] = $org['vacation_balance_initial'];
                }

                if (!empty($org['department'])) {
                    $transformedOrg['department'] = trim((string) $org['department']);
                }

                if (!empty($org['position'])) {
                    $transformedOrg['position'] = trim((string) $org['position']);
                }

                $transformedOrgs[] = $transformedOrg;
            }

            $user['organizaciones'] = $transformedOrgs;
            return $user;
        }, $users);
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
            $consolidationResult = $this->consolidateDuplicates($parsedData);
            $consolidated = $consolidationResult['data'];
            $errors = array_merge($errors, $consolidationResult['errors']);

            // Validar emails que ya existen en BD. La carga masiva solo da de
            // alta usuarios nuevos, así que un usuario existente es un error
            // que hay que resolver quitando la fila (ver ProcessUserChunk).
            $duplicateEmails = $this->checkDuplicateEmails($consolidated);

            // Agregar errores de emails duplicados
            foreach ($duplicateEmails as $duplicate) {
                $errors[] = [
                    'row' => $duplicate['row'] ?? 0,
                    'field' => 'email',
                    'message' => "El email '{$duplicate['email']}' ya existe en el sistema para el usuario: {$duplicate['existing_user']}. La carga masiva solo da de alta usuarios nuevos: quita esta fila para continuar.",
                ];
            }

            // Validar documentos que ya existen en BD
            $duplicateDocuments = $this->checkDuplicateDocuments($consolidated);

            // Agregar errores de documentos duplicados
            foreach ($duplicateDocuments as $duplicate) {
                $errors[] = [
                    'row' => $duplicate['row'] ?? 0,
                    'field' => 'numero_documento',
                    'message' => "El documento '{$duplicate['document']}' ya existe en el sistema para el usuario: {$duplicate['existing_user']}. La carga masiva solo da de alta usuarios nuevos: quita esta fila para continuar.",
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
                // Retornar parsedData para que el usuario pueda editar, incluso si hay errores
                'data' => count($parsedData) > 0 ? $parsedData : $consolidated,
                'consolidated_data' => $consolidated,
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
    // VALIDACIÓN DE DATOS JSON (sin archivo)
    // ────────────────────────────────────────────────────────────

    /**
     * Validar datos JSON (usuarios editados)
     * Similar a validateFile pero sin parsear archivo
     */
    public function validateData(array $users): array
    {
        try {
            $errors = [];
            $warnings = [];
            $validUsers = [];

            // Roles operativos permitidos según quién sube el grid
            // [OBS-CLIENTE 2026-08]: se resuelve una sola vez por request,
            // no por fila (el actor no cambia entre filas del mismo batch).
            $allowedRoles = self::allowedOrgRolesFor(Auth::user());

            // Validar cada usuario
            foreach ($users as $index => $user) {
                $rowNumber = $user['row_number'] ?? $index + 2;
                $rowErrors = $this->validateUserRow($user, $rowNumber, $allowedRoles);
                $errors = array_merge($errors, $rowErrors);
            }

            // Verificar emails duplicados dentro del mismo lote
            $emailsInBatch = $this->checkDuplicateEmailsInBatch($users);
            $errors = array_merge($errors, $emailsInBatch);

            // Verificar documentos duplicados dentro del mismo lote
            $documentsInBatch = $this->checkDuplicateDocumentsInBatch($users);
            $errors = array_merge($errors, $documentsInBatch);

            // Consolidar duplicados (mismo email)
            $consolidationResult = $this->consolidateDuplicates($users);
            $consolidated = $consolidationResult['data'];
            $errors = array_merge($errors, $consolidationResult['errors']);

            // Validar emails que ya existen en BD. La carga masiva solo da de
            // alta usuarios nuevos, así que un usuario existente es un error
            // que hay que resolver quitando la fila (ver ProcessUserChunk).
            $duplicateEmails = $this->checkDuplicateEmails($consolidated);
            foreach ($duplicateEmails as $duplicate) {
                $errors[] = [
                    'row' => $duplicate['row'] ?? 0,
                    'field' => 'email',
                    'message' => "El email '{$duplicate['email']}' ya existe en el sistema para el usuario: {$duplicate['existing_user']}. La carga masiva solo da de alta usuarios nuevos: quita esta fila para continuar.",
                ];
            }

            // Validar documentos que ya existen en BD
            $duplicateDocuments = $this->checkDuplicateDocuments($consolidated);
            foreach ($duplicateDocuments as $duplicate) {
                $errors[] = [
                    'row' => $duplicate['row'] ?? 0,
                    'field' => 'numero_documento',
                    'message' => "El documento '{$duplicate['document']}' ya existe en el sistema para el usuario: {$duplicate['existing_user']}. La carga masiva solo da de alta usuarios nuevos: quita esta fila para continuar.",
                ];
            }

            // Generar resumen
            $summary = [
                'total' => count($users),
                'valid' => count($consolidated) - count($duplicateEmails) - count($duplicateDocuments),
                'errors' => count($errors),
                'warnings' => count($warnings),
                'duplicate_emails' => count($duplicateEmails),
                'duplicate_documents' => count($duplicateDocuments),
            ];

            $isValid = count($errors) === 0 && count($consolidated) > 0;

            return [
                'valid' => $isValid,
                'data' => $consolidated,
                'errors' => $errors,
                'warnings' => $warnings,
                'summary' => $summary,
            ];

        } catch (\Exception $e) {
            Log::error('Data validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'valid' => false,
                'data' => [],
                'errors' => [
                    [
                        'row' => 0,
                        'field' => 'data',
                        'message' => 'Error al validar datos: ' . $e->getMessage(),
                    ]
                ],
                'warnings' => [],
                'summary' => [
                    'total' => count($users),
                    'valid' => 0,
                    'errors' => 1,
                    'warnings' => 0,
                ],
            ];
        }
    }

    /**
     * Validar una fila de usuario
     *
     * @param array<string> $allowedRoles Roles operativos por organización
     *   permitidos para el actor que sube el grid (ver allowedOrgRolesFor).
     */
    private function validateUserRow(array $user, int $rowNumber, array $allowedRoles): array
    {
        $errors = [];

        // Campos requeridos
        $required = ['nombre', 'apellido', 'email', 'tipo_documento', 'numero_documento', 'estado'];
        foreach ($required as $field) {
            if (empty($user[$field])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => $field,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' es requerido',
                ];
            }
        }

        // Validar email
        if (!empty($user['email']) && !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => 'email',
                'message' => 'Email inválido',
            ];
        }

        // Validar tipo_documento
        if (!empty($user['tipo_documento']) && !in_array($user['tipo_documento'], ['dni', 'ce', 'passport', 'ruc'])) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => 'tipo_documento',
                'message' => 'Tipo de documento inválido',
            ];
        }

        // Validar numero_documento según tipo
        $tipoDoc = $user['tipo_documento'] ?? '';
        $numDoc = trim($user['numero_documento'] ?? '');
        
        if (!empty($numDoc) && !empty($tipoDoc)) {
            switch ($tipoDoc) {
                case 'dni':
                    if (strlen($numDoc) !== 8) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'numero_documento',
                            'message' => 'El DNI debe tener 8 dígitos',
                        ];
                    } elseif (!ctype_digit($numDoc)) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'numero_documento',
                            'message' => 'El DNI solo debe contener números',
                        ];
                    }
                    break;
                    
                case 'ruc':
                    if (strlen($numDoc) !== 11) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'numero_documento',
                            'message' => 'El RUC debe tener 11 dígitos',
                        ];
                    } elseif (!ctype_digit($numDoc)) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'numero_documento',
                            'message' => 'El RUC solo debe contener números',
                        ];
                    }
                    break;
                    
                case 'ce':
                    if (strlen($numDoc) !== 12) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'numero_documento',
                            'message' => 'El Carné de Extranjería debe tener 12 caracteres',
                        ];
                    } elseif (!ctype_digit($numDoc)) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'numero_documento',
                            'message' => 'El Carné de Extranjería solo debe contener números',
                        ];
                    }
                    break;
                    
                case 'passport':
                    // Pasaporte: mínimo 6, máximo 20 caracteres alfanuméricos
                    if (strlen($numDoc) < 6 || strlen($numDoc) > 20) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'numero_documento',
                            'message' => 'El pasaporte debe tener entre 6 y 20 caracteres',
                        ];
                    } elseif (!ctype_alnum($numDoc)) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'numero_documento',
                            'message' => 'El pasaporte solo debe contener letras y números',
                        ];
                    }
                    break;
            }
        }

        // Validar estado
        if (!empty($user['estado']) && !in_array($user['estado'], ['active', 'inactive'])) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => 'estado',
                'message' => 'Estado inválido',
            ];
        }

        // Toda fila debe traer al menos una empresa: el rol del usuario vive
        // en la empresa (user_tenant_roles), así que una fila sin empresa
        // crearía un usuario sin empresas y sin ningún rol. El único rol
        // global es 'root', que no se da de alta por carga masiva.
        $hasValidOrg = false;
        if (!empty($user['organizaciones']) && is_array($user['organizaciones'])) {
            foreach ($user['organizaciones'] as $org) {
                if (!empty($org['ruc']) && is_string($org['ruc']) && trim($org['ruc']) !== '') {
                    $hasValidOrg = true;
                    break;
                }
            }
        }

        if (!$hasValidOrg) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => 'organizaciones',
                'message' => 'Debes asignar al menos una empresa',
            ];
        }

        // Validar organizaciones (si existen)
        if (!empty($user['organizaciones'])) {
            foreach ($user['organizaciones'] as $orgIndex => $org) {
                // Una organización sin RUC es una fila en blanco del grid: no
                // se valida ni se le exige rol (mismo criterio que
                // UsersImport::parseOrganizations).
                if (empty($org['ruc']) || trim((string) $org['ruc']) === '') {
                    continue;
                }

                // Verificar que la organización existe
                $tenant = Tenant::where('ruc', $org['ruc'])->first();
                if (!$tenant) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => "organizaciones.{$orgIndex}.ruc",
                        'message' => "Organización con RUC '{$org['ruc']}' no existe",
                    ];
                } else if (!empty($org['supervisor_email'])) {
                    // Verificar que el supervisor existe y pertenece a la org
                    $supervisor = User::where('email', $org['supervisor_email'])
                        ->whereHas('tenants', fn($q) => $q->where('tenants.id', $tenant->id))
                        ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
                        ->first();

                    if (!$supervisor) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => "organizaciones.{$orgIndex}.supervisor_email",
                            'message' => "Supervisor '{$org['supervisor_email']}' no existe o no pertenece a la organización",
                        ];
                    }
                }

                // Rol(es) operativos por organización (RP1-C). Requerido: es
                // el único lugar donde la carga masiva asigna roles, así que
                // sin él el usuario quedaría en la empresa sin permisos.
                $orgRoles = [];
                if (!empty($org['roles'])) {
                    $orgRoles = is_array($org['roles'])
                        ? $org['roles']
                        : explode(',', (string) $org['roles']);

                    $orgRoles = array_values(array_filter(array_map(
                        fn($roleName) => strtolower(trim((string) $roleName)),
                        $orgRoles
                    )));
                }

                if (empty($orgRoles)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => "organizaciones.{$orgIndex}.roles",
                        'message' => 'Rol requerido en organización ' . ($orgIndex + 1)
                            . ' (permitidos: ' . implode(', ', $allowedRoles) . ')',
                    ];
                }

                foreach ($orgRoles as $roleName) {
                    if (!in_array($roleName, $allowedRoles, true)) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => "organizaciones.{$orgIndex}.roles",
                            'message' => "Rol '{$roleName}' inválido en organización " . ($orgIndex + 1) . ' (permitidos: ' . implode(', ', $allowedRoles) . ')',
                        ];
                    }
                }

                // Fecha de ingreso por organización
                if (!empty($org['hire_date'])) {
                    if (strtotime((string) $org['hire_date']) === false) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => "organizaciones.{$orgIndex}.hire_date",
                            'message' => 'Fecha de ingreso inválida en organización ' . ($orgIndex + 1),
                        ];
                    }
                }

                // Saldo inicial de vacaciones por organización
                if (isset($org['vacation_balance_initial']) && $org['vacation_balance_initial'] !== null && $org['vacation_balance_initial'] !== '') {
                    if (!is_numeric($org['vacation_balance_initial'])) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => "organizaciones.{$orgIndex}.vacation_balance_initial",
                            'message' => 'Saldo de vacaciones inválido en organización ' . ($orgIndex + 1) . ' (debe ser numérico)',
                        ];
                    } elseif ((float) $org['vacation_balance_initial'] < 0) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => "organizaciones.{$orgIndex}.vacation_balance_initial",
                            'message' => 'Saldo de vacaciones no puede ser negativo en organización ' . ($orgIndex + 1),
                        ];
                    }
                }

                // Departamento/cargo por organización (RP-B3)
                if (!empty($org['department']) && strlen((string) $org['department']) > 255) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => "organizaciones.{$orgIndex}.department",
                        'message' => 'Departamento inválido en organización ' . ($orgIndex + 1) . ' (máx. 255 caracteres)',
                    ];
                }

                if (!empty($org['position']) && strlen((string) $org['position']) > 255) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => "organizaciones.{$orgIndex}.position",
                        'message' => 'Cargo inválido en organización ' . ($orgIndex + 1) . ' (máx. 255 caracteres)',
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * Verificar emails duplicados dentro del mismo lote
     */
    private function checkDuplicateEmailsInBatch(array $users): array
    {
        $errors = [];
        $emailsSeen = [];

        foreach ($users as $index => $user) {
            $email = strtolower(trim($user['email'] ?? ''));
            $rowNumber = $user['row_number'] ?? $index + 2;

            if (empty($email)) {
                continue;
            }

            if (isset($emailsSeen[$email])) {
                // Email duplicado - agregar error
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'email',
                    'message' => "Email duplicado en el archivo: '{$email}' ya aparece en la fila {$emailsSeen[$email]}",
                ];
            } else {
                $emailsSeen[$email] = $rowNumber;
            }
        }

        return $errors;
    }

    /**
     * Verificar documentos duplicados dentro del mismo lote
     */
    private function checkDuplicateDocumentsInBatch(array $users): array
    {
        $errors = [];
        $documentsSeen = [];

        foreach ($users as $index => $user) {
            $tipoDoc = trim($user['tipo_documento'] ?? '');
            $numDoc = trim($user['numero_documento'] ?? '');
            $rowNumber = $user['row_number'] ?? $index + 2;

            if (empty($numDoc)) {
                continue;
            }

            // Clave única: tipo_documento + numero_documento
            $docKey = strtolower($tipoDoc . ':' . $numDoc);

            if (isset($documentsSeen[$docKey])) {
                // Documento duplicado - agregar error
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'numero_documento',
                    'message' => "Documento duplicado en el archivo: '{$tipoDoc} {$numDoc}' ya aparece en la fila {$documentsSeen[$docKey]}",
                ];
            } else {
                $documentsSeen[$docKey] = $rowNumber;
            }
        }

        return $errors;
    }

    // ────────────────────────────────────────────────────────────
    // CONSOLIDACIÓN DE DUPLICADOS
    // ────────────────────────────────────────────────────────────

    /**
     * Consolidar usuarios con email duplicado en el archivo
     * Retorna array con 'data' (usuarios consolidados) y 'errors' (inconsistencias)
     */
    public function consolidateDuplicates(array $users): array
    {
        $consolidated = [];
        $emailMap = [];
        $errors = [];

        foreach ($users as $user) {
            $email = strtolower($user['email'] ?? '');
            
            if (empty($email)) {
                // Usuario sin email - agregarlo como está
                $consolidated[] = $user;
                continue;
            }

            if (isset($emailMap[$email])) {
                // Usuario duplicado - verificar consistencia y consolidar organizaciones
                $existingIndex = $emailMap[$email];

                // Verificar que datos básicos coincidan (sin lanzar excepción)
                $inconsistencies = $this->checkDuplicateConsistency($consolidated[$existingIndex], $user);
                
                if (!empty($inconsistencies)) {
                    // Hay inconsistencias - agregar errores para ambas filas
                    foreach ($inconsistencies as $field) {
                        $errors[] = [
                            'row' => $user['row_number'] ?? 0,
                            'field' => $field,
                            'message' => "Email duplicado '{$email}': el campo '{$field}' no coincide con la fila " . ($consolidated[$existingIndex]['row_number'] ?? '?'),
                        ];
                    }
                }

                // Agregar organizaciones de todas formas (para mostrar en el editor)
                $consolidated[$existingIndex]['organizaciones'] = array_merge(
                    $consolidated[$existingIndex]['organizaciones'] ?? [],
                    $user['organizaciones'] ?? []
                );
            } else {
                // Nuevo usuario
                $emailMap[$email] = count($consolidated);
                $consolidated[] = $user;
            }
        }

        return [
            'data' => $consolidated,
            'errors' => $errors,
        ];
    }

    /**
     * Verificar consistencia de datos en usuarios duplicados
     * Retorna array de campos que no coinciden
     */
    private function checkDuplicateConsistency(array $existing, array $new): array
    {
        // No se compara el rol: es por empresa (organizaciones[].roles), y dos
        // filas del mismo usuario describen empresas distintas, así que roles
        // distintos son lo esperado, no una inconsistencia.
        $fieldsToCheck = ['nombre', 'apellido', 'tipo_documento', 'numero_documento', 'estado'];
        $inconsistentFields = [];

        foreach ($fieldsToCheck as $field) {
            $existingValue = $existing[$field] ?? '';
            $newValue = $new[$field] ?? '';
            
            if ($existingValue !== $newValue) {
                $inconsistentFields[] = $field;
            }
        }

        return $inconsistentFields;
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
        $existingUsers = User::whereIn(DB::raw('LOWER(email)'), $emails)
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
