<?php

namespace App\Exports\Sheets;

use App\Models\User;
use App\Services\BulkUserUploadService;
use App\Support\BulkUserColumns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class ValidationRulesSheet implements FromArray, WithTitle, WithEvents
{
    /** Encabezados de sección en la columna A de esta hoja. */
    private const SECTION_RUCS = 'RUCs Disponibles';
    private const SECTION_DOC_TYPES = 'Tipos de Documento';
    private const SECTION_STATUSES = 'Estados';
    private const SECTION_SUPERVISORS = 'Supervisores por Organización';
    private const SECTION_ORG_ROLES = 'Roles por Organización';

    private const DOC_TYPES = ['dni', 'ce', 'passport', 'ruc'];
    private const STATUSES = ['active', 'inactive'];

    // Roles operativos asignables por organización (org{n}_rol): fuente única
    // en BulkUserUploadService::allowedOrgRolesFor($actor). [OBS-CLIENTE
    // 2026-07] sacó 'admin_tenant' para todos; [OBS-CLIENTE 2026-08] lo
    // repuso solo para quien descarga la plantilla siendo root.

    private array $organizations;
    private array $supervisorsByOrg;
    private int $maxOrganizations;
    private array $allowedOrgRoles;

    public function __construct(array $organizations, array $supervisorsByOrg, int $maxOrganizations, ?User $actor = null)
    {
        $this->organizations = $organizations;
        $this->supervisorsByOrg = $supervisorsByOrg;
        $this->maxOrganizations = $maxOrganizations;
        $this->allowedOrgRoles = BulkUserUploadService::allowedOrgRolesFor($actor);
    }

    public function title(): string
    {
        return 'Validaciones';
    }

    /**
     * Datos de validación (listas para dropdowns).
     *
     * Cada sección arranca con un encabezado en la columna A; registerEvents
     * localiza los rangos buscando ese texto (ver findSectionRange) en vez de
     * recalcular offsets a mano, para que agregar o quitar una sección no
     * descuadre a las demás.
     */
    public function array(): array
    {
        $data = [];

        // Sección 1: Lista de RUCs
        $data[] = [self::SECTION_RUCS];
        foreach ($this->organizations as $org) {
            $data[] = [$org['ruc'] ?? ''];
        }

        $data[] = [''];
        $data[] = [''];

        // Sección 2: Lista de tipos de documento
        $data[] = [self::SECTION_DOC_TYPES];
        foreach (self::DOC_TYPES as $docType) {
            $data[] = [$docType];
        }

        $data[] = [''];
        $data[] = [''];

        // Sección 3: Lista de estados
        $data[] = [self::SECTION_STATUSES];
        foreach (self::STATUSES as $status) {
            $data[] = [$status];
        }

        // Sección 4: Emails de supervisores por organización
        $data[] = [''];
        $data[] = [''];
        $data[] = [self::SECTION_SUPERVISORS];

        foreach ($this->organizations as $org) {
            $data[] = ["RUC: {$org['ruc']}"];
            $data[] = ['(vacío - sin supervisor)'];

            $supervisors = $this->supervisorsByOrg[$org['id']] ?? [];
            foreach ($supervisors as $supervisor) {
                $data[] = [$supervisor['email']];
            }

            $data[] = [''];
        }

        // Sección 5: roles operativos asignables por organización (org{n}_rol)
        // Se listan por display name (Obs 2: "Empleado" en vez de "client"),
        // que es lo que puebla el dropdown de la columna. El import acepta
        // ambos (ver BulkUserUploadService::orgRoleSlug), así que un archivo
        // con el slug crudo (formato anterior) sigue siendo válido.
        $data[] = [''];
        $data[] = [''];
        $data[] = [self::SECTION_ORG_ROLES];
        foreach ($this->allowedOrgRoles as $role) {
            $data[] = [BulkUserUploadService::orgRoleLabel($role)];
        }

        return $data;
    }

    /**
     * Aplicar validaciones a la hoja de Usuarios
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $usersSheet = $event->sheet->getParent()->getSheetByName('Usuarios');

                if (!$usersSheet) {
                    return;
                }

                // Máximo de filas de usuarios que admite el archivo.
                $lastRow = BulkUserColumns::MAX_USER_ROWS;

                $rucRange = $this->findSectionRange($sheet, self::SECTION_RUCS, count($this->organizations));
                $docTypeRange = $this->findSectionRange($sheet, self::SECTION_DOC_TYPES, count(self::DOC_TYPES));
                $statusRange = $this->findSectionRange($sheet, self::SECTION_STATUSES, count(self::STATUSES));
                $orgRolesRange = $this->findSectionRange($sheet, self::SECTION_ORG_ROLES, count($this->allowedOrgRoles));

                if ($docTypeRange) {
                    $this->applyDropdown($usersSheet, $this->columnRange('tipo_documento', $lastRow), $docTypeRange);
                }

                if ($statusRange) {
                    $this->applyDropdown($usersSheet, $this->columnRange('estado', $lastRow), $statusRange);
                }

                for ($i = 1; $i <= $this->maxOrganizations; $i++) {
                    if ($rucRange) {
                        $this->applyDropdown($usersSheet, $this->columnRange("org{$i}_ruc", $lastRow), $rucRange);
                    }

                    $this->applySupervisorDropdown(
                        $usersSheet,
                        $this->columnRange("org{$i}_supervisor_email", $lastRow)
                    );

                    if ($orgRolesRange) {
                        // Rol(es): lista de referencia, sin bloquear (permite
                        // ingresar varios separados por coma, ej: "admin,aprobador")
                        $this->applyDropdown(
                            $usersSheet,
                            $this->columnRange("org{$i}_rol", $lastRow),
                            $orgRolesRange,
                            DataValidation::STYLE_INFORMATION
                        );
                    }

                    // Las columnas de fecha no llevan validación: cualquier
                    // mínimo sería arbitrario (una fecha de ingreso puede ser
                    // tan antigua como la antigüedad del trabajador) y el
                    // backend no impone ninguno. Basta con que
                    // UsersSheetTemplate las formatee como fecha para que lo
                    // tipeado se guarde como fecha; validar es cosa del
                    // backend al subir el archivo (UsersImport::parseExcelDate).
                    $this->applyDecimalValidation($usersSheet, $this->columnRange("org{$i}_saldo_vacaciones", $lastRow));
                }
            },
        ];
    }

    /**
     * Rango tipo "F2:F1000" de la columna de una clave canónica en la hoja
     * Usuarios.
     */
    private function columnRange(string $key, int $lastRow): string
    {
        $column = BulkUserColumns::letterFor($key, $this->maxOrganizations);

        return "{$column}2:{$column}{$lastRow}";
    }

    /**
     * Localizar los valores de una sección de esta hoja por su encabezado en
     * la columna A y devolver la referencia absoluta a sus $count filas de
     * valores (ej. "Validaciones!$A$14:$A$17"). Null si la sección no existe
     * o no tiene valores.
     */
    private function findSectionRange($sheet, string $header, int $count): ?string
    {
        if ($count < 1) {
            return null;
        }

        foreach ($sheet->getRowIterator() as $row) {
            $index = $row->getRowIndex();

            if ($sheet->getCell('A' . $index)->getValue() === $header) {
                $start = $index + 1;
                $end = $start + $count - 1;

                return "Validaciones!\$A\${$start}:\$A\${$end}";
            }
        }

        return null;
    }

    /**
     * Dropdown de supervisores.
     *
     * Lo ideal sería una lista dependiente del RUC elegido en la fila (vía
     * INDIRECT), pero PhpSpreadsheet no lo soporta de forma simple, así que
     * se ofrece la lista de TODOS los supervisores como ayuda de tipeo, sin
     * bloquear. El chequeo real de "este supervisor pertenece a esa empresa"
     * lo hace UsersImport::parseOrganizations al validar el archivo subido.
     */
    private function applySupervisorDropdown($sheet, string $range): void
    {
        $allSupervisors = [];
        foreach ($this->supervisorsByOrg as $supervisors) {
            foreach ($supervisors as $supervisor) {
                if (!in_array($supervisor['email'], $allSupervisors, true)) {
                    $allSupervisors[] = $supervisor['email'];
                }
            }
        }

        if (empty($allSupervisors)) {
            return;
        }

        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"' . implode(',', $allSupervisors) . '"');
        $validation->setShowErrorMessage(false);

        $sheet->setDataValidation($range, $validation);
    }

    /**
     * Helper para aplicar dropdown de validación.
     *
     * @param string $errorStyle DataValidation::STYLE_STOP bloquea valores
     *   fuera de la lista; STYLE_INFORMATION/STYLE_WARNING solo advierten
     *   (necesario para org{n}_rol, que admite varios valores separados por
     *   coma y por lo tanto no siempre calza exactamente con un ítem de la lista).
     */
    private function applyDropdown($sheet, string $range, string $formula, string $errorStyle = DataValidation::STYLE_STOP): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle($errorStyle);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1($formula);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valor inválido');
        $validation->setError(
            $errorStyle === DataValidation::STYLE_STOP
                ? 'Por favor seleccione un valor de la lista desplegable.'
                : 'Puedes seleccionar de la lista o escribir varios valores separados por coma (ej: admin,aprobador).'
        );

        // Aplicar validación al rango completo
        $sheet->setDataValidation($range, $validation);
    }

    /**
     * Validación numérica (>= 0) para org{n}_saldo_vacaciones (solo advierte, no bloquea).
     */
    private function applyDecimalValidation($sheet, string $range): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_DECIMAL);
        $validation->setErrorStyle(DataValidation::STYLE_WARNING);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valor inválido');
        $validation->setError('El saldo de vacaciones debe ser un número mayor o igual a 0.');
        $validation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
        $validation->setFormula1('0');

        $sheet->setDataValidation($range, $validation);
    }
}
