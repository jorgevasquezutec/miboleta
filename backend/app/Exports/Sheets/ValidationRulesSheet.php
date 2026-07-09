<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;

class ValidationRulesSheet implements FromArray, WithTitle, WithEvents
{
    private array $organizations;
    private array $supervisorsByOrg;
    private int $maxOrganizations;

    public function __construct(array $organizations, array $supervisorsByOrg, int $maxOrganizations)
    {
        $this->organizations = $organizations;
        $this->supervisorsByOrg = $supervisorsByOrg;
        $this->maxOrganizations = $maxOrganizations;
    }

    public function title(): string
    {
        return 'Validaciones';
    }

    /**
     * Datos de validación (listas para dropdowns)
     */
    public function array(): array
    {
        $data = [];

        // Sección 1: Lista de RUCs
        $data[] = ['RUCs Disponibles'];
        foreach ($this->organizations as $org) {
            $data[] = [$org['ruc'] ?? ''];
        }

        // Espacio
        $data[] = [''];
        $data[] = [''];

        // Sección 2: Lista de tipos de documento
        $data[] = ['Tipos de Documento'];
        $data[] = ['dni'];
        $data[] = ['ce'];
        $data[] = ['passport'];
        $data[] = ['ruc'];

        // Espacio
        $data[] = [''];
        $data[] = [''];

        // Sección 3: Lista de roles
        $data[] = ['Roles'];
        $data[] = ['client'];
        $data[] = ['root'];
        $data[] = ['admin'];

        // Espacio
        $data[] = [''];
        $data[] = [''];

        // Sección 4: Lista de estados
        $data[] = ['Estados'];
        $data[] = ['active'];
        $data[] = ['inactive'];

        // Sección 5: Emails de supervisores por organización
        $data[] = [''];
        $data[] = [''];
        $data[] = ['Supervisores por Organización'];

        foreach ($this->organizations as $org) {
            $data[] = ["RUC: {$org['ruc']}"];
            $data[] = ['(vacío - sin supervisor)'];

            $supervisors = $this->supervisorsByOrg[$org['id']] ?? [];
            foreach ($supervisors as $supervisor) {
                $data[] = [$supervisor['email']];
            }

            $data[] = [''];
        }

        // Sección 6 (RP1-C): roles operativos asignables por organización
        // (org{n}_rol). 'root' queda excluido: es un rol global.
        $data[] = [''];
        $data[] = [''];
        $data[] = ['Roles por Organización'];
        $data[] = ['admin'];
        $data[] = ['client'];
        $data[] = ['aprobador'];
        $data[] = ['admin_tenant'];

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

                // Obtener el último row con datos
                $lastRow = 1000; // Max 1000 usuarios
    
                // Calcular posiciones dinámicas basadas en cantidad de orgs
                $numOrgs = count($this->organizations);

                // Fila 1: "RUCs Disponibles"
                // Filas 2 a (1 + numOrgs): RUCs
                $rucStartRow = 2;
                $rucEndRow = 1 + $numOrgs;

                // Después de RUCs: 2 filas vacías
                $currentRow = $rucEndRow + 3; // +2 vacías +1 para siguiente header
    
                // Tipos de documento: header en $currentRow, valores empiezan en $currentRow + 1
                $docTypeStartRow = $currentRow + 1;
                $docTypeEndRow = $docTypeStartRow + 3; // 4 valores: dni, ce, passport, ruc
                $currentRow = $docTypeEndRow + 3; // +2 vacías +1 para siguiente header
    
                // Roles: header en $currentRow, valores empiezan en $currentRow + 1
                $roleStartRow = $currentRow + 1;
                $roleEndRow = $roleStartRow + 2; // 3 valores: client, root, admin
                $currentRow = $roleEndRow + 3; // +2 vacías +1 para siguiente header
    
                // Estados: header en $currentRow, valores empiezan en $currentRow + 1
                $statusStartRow = $currentRow + 1;
                $statusEndRow = $statusStartRow + 1; // 2 valores: active, inactive
    
                // Columna D: tipo_documento
                $this->applyDropdown($usersSheet, "D2:D{$lastRow}", "Validaciones!\$A\${$docTypeStartRow}:\$A\${$docTypeEndRow}");

                // Columna F: rol
                $this->applyDropdown($usersSheet, "F2:F{$lastRow}", "Validaciones!\$A\${$roleStartRow}:\$A\${$roleEndRow}");

                // Columna G: estado
                $this->applyDropdown($usersSheet, "G2:G{$lastRow}", "Validaciones!\$A\${$statusStartRow}:\$A\${$statusEndRow}");

                // Columnas de org_ruc (dinámico según maxOrganizations)
                $rucRange = "Validaciones!\$A\${$rucStartRow}:\$A\${$rucEndRow}";

                $column = 'I'; // Primera org (columna I)
                for ($i = 1; $i <= $this->maxOrganizations; $i++) {
                    $this->applyDropdown($usersSheet, "{$column}2:{$column}{$lastRow}", $rucRange);
                    $column++; // Ahora en columna supervisor_email
    
                    // Aplicar validación dependiente para supervisor_email
                    // Usamos INDIRECT para referenciar dinámicamente el rango según el RUC seleccionado
                    $rucColumn = chr(ord($column) - 1); // Columna del RUC (la anterior)
                    $this->applyDependentDropdown(
                        $usersSheet,
                        "{$column}2:{$column}{$lastRow}",
                        $rucColumn,
                        $this->organizations,
                        $this->supervisorsByOrg
                    );

                    $column++; // Siguiente org_ruc
                }

                // Bloque nuevo (RP1-C): org{n}_rol / org{n}_fecha_ingreso /
                // org{n}_saldo_vacaciones, ubicado después del bloque
                // ruc/supervisor (no altera las columnas ya existentes).
                $orgRolesStartRow = null;
                foreach ($sheet->getRowIterator() as $row) {
                    $cellValue = $sheet->getCell('A' . $row->getRowIndex())->getValue();
                    if ($cellValue === 'Roles por Organización') {
                        $orgRolesStartRow = $row->getRowIndex() + 1;
                        break;
                    }
                }

                if ($orgRolesStartRow) {
                    $orgRolesEndRow = $orgRolesStartRow + 3; // admin, client, aprobador, admin_tenant
                    $orgRolesRange = "Validaciones!\$A\${$orgRolesStartRow}:\$A\${$orgRolesEndRow}";

                    $colIndex = 8 + (2 * $this->maxOrganizations) + 1; // primera columna del bloque nuevo
                    for ($i = 1; $i <= $this->maxOrganizations; $i++) {
                        $rolColumn = Coordinate::stringFromColumnIndex($colIndex);
                        $fechaColumn = Coordinate::stringFromColumnIndex($colIndex + 1);
                        $saldoColumn = Coordinate::stringFromColumnIndex($colIndex + 2);

                        // Rol(es): lista de referencia, sin bloquear (permite
                        // ingresar varios separados por coma, ej: "admin,aprobador")
                        $this->applyDropdown(
                            $usersSheet,
                            "{$rolColumn}2:{$rolColumn}{$lastRow}",
                            $orgRolesRange,
                            DataValidation::STYLE_INFORMATION
                        );

                        $this->applyDateValidation($usersSheet, "{$fechaColumn}2:{$fechaColumn}{$lastRow}");
                        $this->applyDecimalValidation($usersSheet, "{$saldoColumn}2:{$saldoColumn}{$lastRow}");

                        $colIndex += 3;
                    }
                }
            },
        ];
    }

    /**
     * Aplicar dropdown dependiente para supervisores
     */
    private function applyDependentDropdown($sheet, string $range, string $rucColumn, array $organizations, array $supervisorsByOrg): void
    {
        // Para cada organización, crear un rango nombrado con sus supervisores
        $workbook = $sheet->getParent();
        $validationsSheet = $workbook->getSheetByName('Validaciones');

        if (!$validationsSheet) {
            return;
        }

        // Encontrar dónde están los supervisores en la hoja Validaciones
        $currentRow = 1;
        $supervisorStartRow = null;

        // Buscar la sección "Supervisores por Organización"
        foreach ($validationsSheet->getRowIterator() as $row) {
            $cellValue = $validationsSheet->getCell('A' . $row->getRowIndex())->getValue();
            if ($cellValue === 'Supervisores por Organización') {
                $supervisorStartRow = $row->getRowIndex() + 1;
                break;
            }
        }

        if (!$supervisorStartRow) {
            // Si no encontramos la sección, permitir escribir libremente
            return;
        }

        // Crear rangos nombrados para cada organización
        $nameManager = $workbook->getNamedRanges();
        $supervisorRow = $supervisorStartRow;

        foreach ($organizations as $org) {
            $ruc = $org['ruc'];
            $orgSupervisors = $supervisorsByOrg[$org['id']] ?? [];

            // Saltar el header de RUC
            $supervisorRow++;

            $startRow = $supervisorRow + 1; // +1 para saltar "(vacío - sin supervisor)"
            $endRow = $startRow + count($orgSupervisors) - 1;

            if (count($orgSupervisors) > 0) {
                // Crear nombre de rango válido (sin caracteres especiales)
                $rangeName = 'SUP_' . preg_replace('/[^A-Za-z0-9]/', '_', $ruc);

                // Crear el rango nombrado
                $namedRange = new \PhpOffice\PhpSpreadsheet\NamedRange(
                    $rangeName,
                    $validationsSheet,
                    "Validaciones!\$A\${$startRow}:\$A\${$endRow}"
                );

                try {
                    $workbook->addNamedRange($namedRange);
                } catch (\Exception $e) {
                    // Si ya existe, continuar
                }
            }

            $supervisorRow += count($orgSupervisors) + 2; // +1 vacío al inicio +1 vacío al final
        }

        // Ahora aplicar la validación INDIRECT
        // NO podemos usar INDIRECT directamente en PhpSpreadsheet de manera simple
        // Por ahora, dejamos que el usuario escriba el email libremente
        // O aplicamos una lista con TODOS los supervisores (menos ideal pero funciona)

        // Opción: Lista con todos los supervisores disponibles
        $allSupervisors = [];
        foreach ($supervisorsByOrg as $supervisors) {
            foreach ($supervisors as $supervisor) {
                if (!in_array($supervisor['email'], $allSupervisors)) {
                    $allSupervisors[] = $supervisor['email'];
                }
            }
        }

        if (count($allSupervisors) > 0) {
            // Crear lista como string separado por comas
            $supervisorList = '"' . implode(',', $allSupervisors) . '"';

            $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1($supervisorList);
            $validation->setShowErrorMessage(false);

            $sheet->setDataValidation($range, $validation);
        }
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
     * Validación de fecha para org{n}_fecha_ingreso (solo advierte, no bloquea).
     */
    private function applyDateValidation($sheet, string $range): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_DATE);
        $validation->setErrorStyle(DataValidation::STYLE_WARNING);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Fecha inválida');
        $validation->setError('Ingresa una fecha válida (ej: 2024-01-15).');
        $validation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
        $validation->setFormula1('DATE(2000,1,1)');

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
