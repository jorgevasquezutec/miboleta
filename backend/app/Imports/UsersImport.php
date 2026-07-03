<?php

namespace App\Imports;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
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
    private const ALLOWED_ORG_ROLES = ['admin', 'client', 'aprobador', 'administrador_clientes'];

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
        } elseif (!in_array($row['rol'], ['client', 'root', 'admin'])) {
            $errors[] = ['field' => 'rol', 'message' => 'Rol inválido (client, root, admin)'];
        }

        if (empty($row['estado'])) {
            $errors[] = ['field' => 'estado', 'message' => 'Estado es requerido'];
        } elseif (!in_array($row['estado'], ['active', 'inactive'])) {
            $errors[] = ['field' => 'estado', 'message' => 'Estado inválido (active, inactive)'];
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
                'organizaciones' => $organizaciones,
            ],
            'warnings' => $warnings,
        ];
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

            $organizaciones[] = [
                'ruc' => $ruc,
                'tenant_id' => $tenant->id,
                'supervisor_email' => $supervisorEmail,
                'supervisor_id' => $supervisorId,
                'roles' => $roles,
                'hire_date' => $hireDate,
                'vacation_balance_initial' => $vacationBalance,
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
