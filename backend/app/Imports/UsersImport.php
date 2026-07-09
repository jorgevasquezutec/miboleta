<?php

namespace App\Imports;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class UsersImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    /**
     * Roles operativos asignables por organización (RP1-C). 'root' queda
     * excluido a propósito: es un rol global, no se asigna por empresa.
     */
    private const ALLOWED_ORG_ROLES = ['admin', 'client', 'aprobador', 'admin_tenant'];

    /**
     * FIX B2.2: roles admitidos en la columna 'rol' (nivel de fila, no por
     * organización). 'root' queda excluido a propósito: solo root puede dar
     * de alta a otro root (ver StoreUserRequest/UpdateUserRequest::FIX B1),
     * y la carga masiva no tiene forma de verificar esa jerarquía por fila.
     */
    private const ALLOWED_TOP_LEVEL_ROLES = ['client', 'admin', 'admin_tenant', 'aprobador'];

    private array $parsedData = [];
    private array $errors = [];
    private array $warnings = [];
    private int $rowNumber = 1; // Start after header
    private bool $isProcessingUsersSheet = true;

    /**
     * Procesar colección de datos del Excel
     */
    public function collection(Collection $rows)
    {
        // Detectar si es una hoja de usuarios verificando headers
        if ($rows->isNotEmpty()) {
            $firstRow = $rows->first();
            $keys = $firstRow->keys()->toArray();

            // Si la hoja no tiene columna "email", no es la hoja de usuarios
            if (!in_array('email', $keys)) {
                $this->isProcessingUsersSheet = false;
                return; // Skip esta hoja
            }

            $this->isProcessingUsersSheet = true;
        }

        // Si no es hoja de usuarios, skip todo
        if (!$this->isProcessingUsersSheet) {
            return;
        }

        foreach ($rows as $row) {
            $this->rowNumber++;

            // Skipear filas completamente vacías
            if ($this->isEmptyRow($row)) {
                continue;
            }

            // Parsear y validar fila
            $result = $this->parseRow($row->toArray(), $this->rowNumber);

            if ($result['valid']) {
                $this->parsedData[] = $result['data'];

                // Agregar warnings si existen
                if (!empty($result['warnings'])) {
                    foreach ($result['warnings'] as $warning) {
                        $this->warnings[] = [
                            'row' => $this->rowNumber,
                            'field' => $warning['field'],
                            'message' => $warning['message'],
                        ];
                    }
                }
            } else {
                // Agregar errores
                foreach ($result['errors'] as $error) {
                    $this->errors[] = [
                        'row' => $this->rowNumber,
                        'field' => $error['field'],
                        'message' => $error['message'],
                    ];
                }
            }
        }
    }

    /**
     * Chunk size para lectura en memoria
     */
    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * Obtener datos parseados
     */
    public function getParsedData(): array
    {
        return $this->parsedData;
    }

    /**
     * Obtener errores encontrados
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Obtener warnings encontrados
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Verificar si fila está vacía
     */
    private function isEmptyRow(Collection $row): bool
    {
        return $row->filter(fn($value) => !empty($value))->isEmpty();
    }

    /**
     * Parsear y validar una fila individual
     */
    private function parseRow(array $row, int $rowNum): array
    {
        $errors = [];
        $warnings = [];

        // ────────────────────────────────────────────────────────
        // ALIAS/NORMALIZACIÓN DE ENCABEZADOS (RP-B4)
        // Traduce el formato de la plantilla del cliente (APEPAT, APEMAT,
        // TIPDOC, DOCIDEN, ORGANIZACIÓN, etc.) a nuestro formato canónico
        // (nombre, apellido, tipo_documento, numero_documento, org1_ruc...)
        // antes de validar. Ver docs/sprintfix/MAPEO-CARGA-MASIVA.md.
        // ────────────────────────────────────────────────────────

        $row = $this->normalizeRow($row, $warnings);

        // ────────────────────────────────────────────────────────
        // VALIDAR CAMPOS BÁSICOS REQUERIDOS
        // ────────────────────────────────────────────────────────

        if (empty($row['nombre'])) {
            $errors[] = ['field' => 'nombre', 'message' => 'Nombre es requerido'];
        }

        if (empty($row['apellido'])) {
            $errors[] = ['field' => 'apellido', 'message' => 'Apellido es requerido'];
        }

        if (empty($row['email'])) {
            $errors[] = ['field' => 'email', 'message' => 'Email es requerido'];
        } elseif (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['field' => 'email', 'message' => 'Email inválido'];
        }

        if (empty($row['tipo_documento'])) {
            $errors[] = ['field' => 'tipo_documento', 'message' => 'Tipo de documento es requerido'];
        } elseif (!in_array($row['tipo_documento'], ['dni', 'ce', 'passport', 'ruc'])) {
            $errors[] = ['field' => 'tipo_documento', 'message' => 'Tipo de documento inválido (dni, ce, passport, ruc)'];
        }

        if (empty($row['numero_documento'])) {
            $errors[] = ['field' => 'numero_documento', 'message' => 'Número de documento es requerido'];
        }

        if (empty($row['rol'])) {
            $errors[] = ['field' => 'rol', 'message' => 'Rol es requerido'];
        } elseif (!in_array($row['rol'], self::ALLOWED_TOP_LEVEL_ROLES, true)) {
            // FIX B2.2: 'root' queda excluido a propósito. Antes, cualquier
            // admin/admin_tenant con acceso a la carga masiva podía subir
            // un Excel con una fila rol=root y, sin más chequeos aguas
            // abajo (ProcessUserChunk::getRoleId mapea 'root' => id 1), el
            // usuario se creaba/actualizaba como root (escalada directa).
            $errors[] = ['field' => 'rol', 'message' => 'Rol inválido (client, admin, admin_tenant, aprobador)'];
        }

        if (empty($row['estado'])) {
            $errors[] = ['field' => 'estado', 'message' => 'Estado es requerido'];
        } elseif (!in_array($row['estado'], ['active', 'inactive'])) {
            $errors[] = ['field' => 'estado', 'message' => 'Estado inválido (active, inactive)'];
        }

        // ────────────────────────────────────────────────────────
        // VALIDAR FECHA DE NACIMIENTO (P1, campo opcional de users)
        // ────────────────────────────────────────────────────────

        $birthDate = null;
        $birthDateRaw = $row['fecha_nacimiento'] ?? $row['birth_date'] ?? null;
        if (!empty($birthDateRaw)) {
            $birthDate = $this->parseExcelDate($birthDateRaw);
            if ($birthDate === null) {
                $errors[] = ['field' => 'fecha_nacimiento', 'message' => 'Fecha de nacimiento inválida'];
            }
        }

        // ────────────────────────────────────────────────────────
        // VALIDAR Y PARSEAR ORGANIZACIONES
        // ────────────────────────────────────────────────────────

        $organizaciones = $this->parseOrganizations($row, $errors);

        // Si no tiene organizaciones, agregar warning
        if (empty($organizaciones) && in_array($row['rol'], ['admin', 'client'])) {
            $warnings[] = [
                'field' => 'organizaciones',
                'message' => 'Usuario sin organizaciones asignadas'
            ];
        }

        // ────────────────────────────────────────────────────────
        // WARNINGS OPCIONALES
        // ────────────────────────────────────────────────────────

        if (empty($row['telefono'])) {
            $warnings[] = ['field' => 'telefono', 'message' => 'Teléfono no especificado'];
        }

        // Verificar si email ya existe en BD
        if (!empty($row['email'])) {
            $existingUser = User::where('email', $row['email'])->first();
            if ($existingUser) {
                $warnings[] = [
                    'field' => 'email',
                    'message' => 'Usuario ya existe en sistema (se actualizará)'
                ];
            }
        }

        // ────────────────────────────────────────────────────────
        // RETORNAR RESULTADO
        // ────────────────────────────────────────────────────────

        if (!empty($errors)) {
            return [
                'valid' => false,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        return [
            'valid' => true,
            'data' => [
                'row_number' => $rowNum,
                'nombre' => trim($row['nombre']),
                'apellido' => trim($row['apellido']),
                'email' => strtolower(trim($row['email'])),
                'tipo_documento' => $row['tipo_documento'],
                'numero_documento' => trim($row['numero_documento']),
                'rol' => $row['rol'],
                'estado' => $row['estado'],
                'telefono' => $row['telefono'] ?? null,
                'birth_date' => $birthDate,
                'organizaciones' => $organizaciones,
            ],
            'warnings' => $warnings,
        ];
    }

    // ────────────────────────────────────────────────────────────
    // ALIAS/NORMALIZACIÓN DE ENCABEZADOS DE LA PLANTILLA DEL CLIENTE (RP-B4)
    // ────────────────────────────────────────────────────────────

    /**
     * Traduce el formato de encabezados de la plantilla real de un cliente
     * (APEPAT/APEMAT/TIPDOC/DOCIDEN/ORGANIZACIÓN/DEPARTAMENTO/CARGO/SALDO DE
     * VACACIONES/PERIODO DE VACACIONES...) a nuestro formato canónico
     * (nombre/apellido/tipo_documento/numero_documento/org1_ruc/...), sin
     * romper compatibilidad con el formato canónico: si una clave canónica
     * ya viene presente y con valor, NO se pisa con el alias.
     *
     * Ver docs/sprintfix/MAPEO-CARGA-MASIVA.md para el detalle completo del
     * mapeo columna cliente → campo interno y las decisiones de
     * interpretación (en particular, ORGANIZACIÓN y PERIODO DE VACACIONES).
     *
     * @param array $row Fila cruda (claves ya slugificadas por WithHeadingRow)
     * @param array $warnings Por referencia: se le agregan warnings de
     *   normalización (ej. organización no resuelta), en el mismo formato
     *   ['field' => ..., 'message' => ...] que usa parseRow().
     * @return array Fila con las claves canónicas agregadas/rellenadas
     */
    private function normalizeRow(array $row, array &$warnings): array
    {
        // 1) Normalizar las CLAVES del array (minúsculas, sin tildes) de
        // forma defensiva, por si el heading row formatter de Laravel Excel
        // no estuviera en modo 'slug' en algún entorno.
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[$this->normalizeHeaderKey((string) $key)] = $value;
        }
        $row = $normalized;

        // 2) apepat + apemat -> apellido
        if (empty($row['apellido']) && (isset($row['apepat']) || isset($row['apemat']))) {
            $apepat = trim((string) ($row['apepat'] ?? ''));
            $apemat = trim((string) ($row['apemat'] ?? ''));
            $row['apellido'] = trim("{$apepat} {$apemat}");
        }

        // 3) tipdoc -> tipo_documento (DNI→dni, CE→ce, PAS/PASAPORTE→passport, RUC→ruc)
        if (empty($row['tipo_documento']) && isset($row['tipdoc'])) {
            $row['tipo_documento'] = $this->normalizeDocumentType($row['tipdoc']);
        }

        // 4) dociden -> numero_documento
        if (empty($row['numero_documento']) && isset($row['dociden'])) {
            $row['numero_documento'] = $row['dociden'];
        }

        // 5) estado: ACTIVO/ACTIVA -> active, INACTIVO/INACTIVA -> inactive
        if (!empty($row['estado'])) {
            $row['estado'] = $this->normalizeEstadoValue($row['estado']);
        }

        // 5b) fecnac/fec_nacimiento -> fecha_nacimiento (P1, alias opcional)
        if (empty($row['fecha_nacimiento'])) {
            if (!empty($row['fecnac'])) {
                $row['fecha_nacimiento'] = $row['fecnac'];
            } elseif (!empty($row['fec_nacimiento'])) {
                $row['fecha_nacimiento'] = $row['fec_nacimiento'];
            }
        }

        // 6) organizacion (nombre/razón social o RUC) -> org1_ruc
        if (empty($row['org1_ruc']) && !empty($row['organizacion'])) {
            $resolvedRuc = $this->resolveOrganizationToRuc((string) $row['organizacion']);
            if ($resolvedRuc !== null) {
                $row['org1_ruc'] = $resolvedRuc;
            } else {
                $warnings[] = [
                    'field' => 'organizacion',
                    'message' => "No se pudo resolver la organización '{$row['organizacion']}' a una empresa registrada (usa el RUC de 11 dígitos o el nombre/razón social exacto).",
                ];
            }
        }

        // 7) departamento/cargo (sin número de organización) -> org1_departamento/org1_cargo
        if (empty($row['org1_departamento']) && !empty($row['departamento'])) {
            $row['org1_departamento'] = $row['departamento'];
        }
        if (empty($row['org1_cargo']) && !empty($row['cargo'])) {
            $row['org1_cargo'] = $row['cargo'];
        }

        // 8) saldo_de_vacaciones -> org1_saldo_vacaciones
        if (empty($row['org1_saldo_vacaciones']) && isset($row['saldo_de_vacaciones']) && $row['saldo_de_vacaciones'] !== '') {
            $row['org1_saldo_vacaciones'] = $row['saldo_de_vacaciones'];
        }

        // 9) periodo_de_vacaciones: se interpreta como el AÑO/PERIODO al que
        // corresponde el saldo inicial de vacaciones. No existe una columna
        // dedicada a "periodo" en el pivote (user_tenants solo tiene
        // vacation_balance_initial), así que NO se persiste tal cual ni
        // genera un devengo adicional (evita duplicar el cálculo de
        // devengo por aniversario). Se usa únicamente como FALLBACK de
        // fecha de ingreso (1 de enero de ese año) cuando la fila no trae
        // una fecha de ingreso explícita, para darle al sistema de vacaciones
        // una fecha de referencia. Si ya viene org1_fecha_ingreso, el
        // periodo se ignora.
        if (empty($row['org1_fecha_ingreso']) && !empty($row['periodo_de_vacaciones'])) {
            $year = $this->extractYearFromPeriod((string) $row['periodo_de_vacaciones']);
            if ($year !== null) {
                $row['org1_fecha_ingreso'] = sprintf('%04d-01-01', $year);
            } else {
                $warnings[] = [
                    'field' => 'periodo_de_vacaciones',
                    'message' => "No se pudo interpretar 'periodo_de_vacaciones' ('{$row['periodo_de_vacaciones']}') como un año; se ignora.",
                ];
            }
        }

        return $row;
    }

    /**
     * Normalizar una clave de encabezado: minúsculas, sin tildes/diacríticos
     * y separadores no alfanuméricos colapsados a guion bajo. Replica lo que
     * hace el heading row formatter 'slug' de Laravel Excel, de forma
     * defensiva por si no estuviera activo.
     */
    private function normalizeHeaderKey(string $key): string
    {
        $key = \Illuminate\Support\Str::ascii(trim($key));
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';

        return trim($key, '_');
    }

    /**
     * Mapear el valor de TIPDOC (DNI/CE/PAS/PASAPORTE/RUC, case-insensitive)
     * a nuestros valores canónicos de tipo_documento. Si no matchea ningún
     * alias conocido, se devuelve el valor original en minúsculas para que
     * la validación estándar lo marque como inválido con un mensaje claro.
     */
    private function normalizeDocumentType($value): string
    {
        $key = strtoupper(trim((string) $value));

        $map = [
            'DNI' => 'dni',
            'CE' => 'ce',
            'PAS' => 'passport',
            'PASAPORTE' => 'passport',
            'PASSPORT' => 'passport',
            'RUC' => 'ruc',
        ];

        return $map[$key] ?? strtolower(trim((string) $value));
    }

    /**
     * Mapear el valor de ESTADO (ACTIVO/ACTIVA/INACTIVO/INACTIVA,
     * case-insensitive) a nuestros valores canónicos active/inactive.
     */
    private function normalizeEstadoValue($value): string
    {
        $key = strtoupper(trim((string) $value));

        $map = [
            'ACTIVO' => 'active',
            'ACTIVA' => 'active',
            'ACTIVE' => 'active',
            'INACTIVO' => 'inactive',
            'INACTIVA' => 'inactive',
            'INACTIVE' => 'inactive',
        ];

        return $map[$key] ?? strtolower(trim((string) $value));
    }

    /**
     * Resolver la columna ORGANIZACIÓN a un RUC: si son 11 dígitos se asume
     * que ya es un RUC; si es texto, se busca un Tenant cuyo name o
     * business_name coincida (case-insensitive). Devuelve null si no
     * resuelve a ninguna empresa registrada.
     */
    private function resolveOrganizationToRuc(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{11}$/', $value)) {
            return $value;
        }

        $normalizedValue = mb_strtolower($value);
        $tenant = Tenant::whereRaw('LOWER(name) = ?', [$normalizedValue])
            ->orWhereRaw('LOWER(business_name) = ?', [$normalizedValue])
            ->first();

        return $tenant?->ruc;
    }

    /**
     * Extraer un año (2000-2100) de un texto de "periodo de vacaciones"
     * (ej. "2024", "2024-2025", "Periodo 2024"). Devuelve null si no se
     * encuentra un año plausible.
     */
    private function extractYearFromPeriod(string $value): ?int
    {
        if (preg_match('/(\d{4})/', $value, $matches)) {
            $year = (int) $matches[1];
            if ($year >= 2000 && $year <= 2100) {
                return $year;
            }
        }

        return null;
    }

    /**
     * Parsear organizaciones de las columnas org1, org2, org3
     *
     * Por organización, además de RUC/supervisor, se admiten (RP1-C):
     * - org{n}_rol (u org{n}_roles): rol(es) operativos, separados por coma
     *   (ej: "admin,aprobador"). Si viene vacío, la fila usará el rol
     *   global (columna 'rol') como fallback para esa empresa.
     * - org{n}_fecha_ingreso: fecha de inicio laboral en esa empresa.
     * - org{n}_saldo_vacaciones: saldo inicial de vacaciones (días) en esa empresa.
     */
    private function parseOrganizations(array $row, array &$errors): array
    {
        $organizaciones = [];

        // Detectar cuántas columnas de organizaciones hay
        for ($i = 1; $i <= 5; $i++) {
            $rucKey = "org{$i}_ruc";
            $supervisorKey = "org{$i}_supervisor_email";
            $rolesKey = "org{$i}_rol";
            $rolesKeyAlt = "org{$i}_roles";
            $hireDateKey = "org{$i}_fecha_ingreso";
            $vacationBalanceKey = "org{$i}_saldo_vacaciones";
            $departmentKey = "org{$i}_departamento";
            $positionKey = "org{$i}_cargo";

            $ruc = $row[$rucKey] ?? null;
            $supervisorEmail = $row[$supervisorKey] ?? null;

            // Si no hay RUC, skipear
            if (empty($ruc)) {
                continue;
            }

            // Validar que RUC existe
            $tenant = Tenant::where('ruc', $ruc)->first();
            if (!$tenant) {
                $errors[] = [
                    'field' => $rucKey,
                    'message' => "RUC {$ruc} no encontrado en sistema"
                ];
                continue;
            }

            // FIX B2-r1 (feedback temprano): cuando quien sube el archivo
            // no es root, debe administrar (rol admin/admin_tenant) el
            // tenant resuelto por RUC. La garantía dura vive en el worker
            // (ver ProcessUserChunk::tenantOwnershipError); esto es solo
            // UX para no esperar a que el job encolado rechace la fila.
            $importer = Auth::user();
            if ($importer && !$importer->isRoot()
                && !$importer->hasRoleInTenant('admin', $tenant->id)
                && !$importer->hasRoleInTenant('admin_tenant', $tenant->id)
            ) {
                $errors[] = [
                    'field' => $rucKey,
                    'message' => "No tienes permisos para asignar usuarios a la empresa con RUC {$ruc}"
                ];
                continue;
            }

            // Validar supervisor si está especificado
            $supervisorId = null;
            if (!empty($supervisorEmail)) {
                $supervisor = User::where('email', $supervisorEmail)
                    ->whereHas('tenants', function ($q) use ($tenant) {
                        $q->where('tenants.id', $tenant->id);
                    })
                    ->first();

                if (!$supervisor) {
                    $errors[] = [
                        'field' => $supervisorKey,
                        'message' => "Supervisor {$supervisorEmail} no encontrado o no pertenece a la organización"
                    ];
                    continue;
                }

                $supervisorId = $supervisor->id;
            }

            // Validar rol(es) operativos para esta organización
            $rolesRaw = $row[$rolesKey] ?? $row[$rolesKeyAlt] ?? null;
            $roles = [];
            if (!empty($rolesRaw)) {
                $roles = array_values(array_filter(array_map(
                    fn($r) => strtolower(trim((string) $r)),
                    explode(',', (string) $rolesRaw)
                )));

                foreach ($roles as $roleName) {
                    if (!in_array($roleName, self::ALLOWED_ORG_ROLES, true)) {
                        $errors[] = [
                            'field' => $rolesKey,
                            'message' => "Rol '{$roleName}' inválido en {$rolesKey} (permitidos: " . implode(', ', self::ALLOWED_ORG_ROLES) . ')',
                        ];
                    }
                }
            }

            // Validar fecha de ingreso a esta organización
            $hireDate = null;
            $hireDateRaw = $row[$hireDateKey] ?? null;
            if (!empty($hireDateRaw)) {
                $hireDate = $this->parseExcelDate($hireDateRaw);
                if ($hireDate === null) {
                    $errors[] = [
                        'field' => $hireDateKey,
                        'message' => "Fecha de ingreso inválida en {$hireDateKey}",
                    ];
                }
            }

            // Validar saldo inicial de vacaciones para esta organización
            $vacationBalance = null;
            $vacationBalanceRaw = $row[$vacationBalanceKey] ?? null;
            if ($vacationBalanceRaw !== null && $vacationBalanceRaw !== '') {
                if (!is_numeric($vacationBalanceRaw)) {
                    $errors[] = [
                        'field' => $vacationBalanceKey,
                        'message' => "Saldo de vacaciones inválido en {$vacationBalanceKey} (debe ser numérico)",
                    ];
                } elseif ((float) $vacationBalanceRaw < 0) {
                    $errors[] = [
                        'field' => $vacationBalanceKey,
                        'message' => "Saldo de vacaciones no puede ser negativo en {$vacationBalanceKey}",
                    ];
                } else {
                    $vacationBalance = (float) $vacationBalanceRaw;
                }
            }

            // Departamento/cargo del usuario en esta organización (RP-B3).
            // Texto libre, sin validación de catálogo.
            $department = isset($row[$departmentKey]) ? trim((string) $row[$departmentKey]) : '';
            $position = isset($row[$positionKey]) ? trim((string) $row[$positionKey]) : '';

            $organizaciones[] = [
                'ruc' => $ruc,
                'tenant_id' => $tenant->id,
                'supervisor_email' => $supervisorEmail,
                'supervisor_id' => $supervisorId,
                'roles' => $roles,
                'hire_date' => $hireDate,
                'vacation_balance_initial' => $vacationBalance,
                'department' => $department !== '' ? $department : null,
                'position' => $position !== '' ? $position : null,
            ];
        }

        return $organizaciones;
    }

    /**
     * Normalizar un valor de celda de Excel a fecha 'Y-m-d'.
     * Soporta: objetos DateTime (cuando PhpSpreadsheet ya castea la celda),
     * números de serie de Excel, y strings de fecha comunes.
     */
    private function parseExcelDate($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
