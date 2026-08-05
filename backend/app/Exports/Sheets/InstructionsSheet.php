<?php

namespace App\Exports\Sheets;

use App\Models\User;
use App\Services\BulkUserUploadService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class InstructionsSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    private int $maxOrganizations;
    private array $allowedOrgRoles;

    public function __construct(int $maxOrganizations, ?User $actor = null)
    {
        $this->maxOrganizations = $maxOrganizations;
        // [OBS-CLIENTE 2026-08]: 'admin_tenant' se lista aquí solo si quien
        // descarga la plantilla es root (ver BulkUserUploadService::
        // allowedOrgRolesFor); admin/admin_tenant siguen sin poder asignarlo.
        $this->allowedOrgRoles = BulkUserUploadService::allowedOrgRolesFor($actor);
    }

    public function title(): string
    {
        return 'Instrucciones';
    }

    /**
     * Roles operativos permitidos, en formato "Empleado (client)" (Obs 2):
     * el display name es lo que el cliente reconoce, el slug entre
     * paréntesis es lo que en la práctica se guarda/valida.
     *
     * @return list<string>
     */
    private function allowedOrgRoleDescriptions(): array
    {
        return array_map(
            fn(string $role) => BulkUserUploadService::orgRoleLabel($role) . " ({$role})",
            $this->allowedOrgRoles
        );
    }

    public function array(): array
    {
        return [
            ['GUÍA DE USO - CARGA MASIVA DE USUARIOS'],
            [''],
            ['📋 PASO 1: COMPLETAR DATOS BÁSICOS'],
            [''],
            ['En la hoja "Usuarios", completa los siguientes campos OBLIGATORIOS:'],
            [''],
            ['• Nombre: Nombre del usuario (ej: Juan)'],
            ['• Apellidos: Apellidos completos (ej: Pérez García)'],
            ['• Correo electrónico: ÚNICO por usuario (ej: juan.perez@empresa.com)'],
            ['• Tipo de documento: Selecciona de la lista desplegable (dni, ce, passport, ruc)'],
            ['• Número de documento: Número según el tipo (ej: 12345678). Esta columna es TEXTO: si tu documento empieza con 0 (ej: 01234567), escríbelo completo y no perderá el cero.'],
            ['• Estado: Selecciona (active o inactive)'],
            [''],
            ['CAMPOS OPCIONALES:'],
            ['• Teléfono: Número de teléfono (ej: +51 999 999 999)'],
            ['• Fecha de nacimiento: (ej: 1990-05-15)'],
            [''],
            [''],
            ['🏢 PASO 2: ASIGNAR EMPRESAS Y ROLES'],
            [''],
            ["Este template está configurado para hasta {$this->maxOrganizations} empresa(s) por usuario."],
            [''],
            ['Cada usuario necesita AL MENOS UNA empresa: el rol se asigna por empresa,'],
            ['así que un usuario sin empresa quedaría sin ningún rol.'],
            [''],
            ['Para cada empresa {N}:'],
            ['1. RUC empresa {N}: (Obligatorio) Selecciona el RUC de la lista desplegable'],
            ['2. Rol en empresa {N}: (Obligatorio) Rol del usuario EN ESA empresa.'],
            ['   Permitidos: ' . implode(', ', $this->allowedOrgRoleDescriptions()) . '.'],
            ['   Para asignar varios roles en la misma empresa, sepáralos con coma'],
            ['   (ej: admin,aprobador).'],
            ['3. Supervisor empresa {N} (correo): (Opcional) Email del supervisor en esa empresa'],
            ['4. Fecha de ingreso empresa {N}: (Opcional) Fecha de inicio laboral en esa empresa (ej: 2024-01-15)'],
            ['5. Saldo de vacaciones empresa {N}: (Opcional) Saldo inicial de vacaciones (en días) en esa empresa'],
            ['6. Departamento empresa {N} / Cargo empresa {N}: (Opcional) Área y puesto en esa empresa'],
            [''],
            ['SOBRE LOS ROLES:'],
            ['• El rol es SIEMPRE por empresa: la misma persona puede ser "client" en una'],
            ['  empresa y "aprobador" en otra. No hay un rol general del usuario.'],
            ['• El rol "root" (administrador de la plataforma) NO se puede crear por esta vía:'],
            ['  se da de alta a mano desde el alta individual de usuarios.'],
            [''],
            ['IMPORTANTE:'],
            ['• Si un usuario pertenece a MÁS empresas, repite el usuario en otra fila'],
            ['• Los datos básicos (nombre, email, etc.) deben ser EXACTAMENTE iguales'],
            ['• El sistema consolidará automáticamente las empresas del mismo email'],
            [''],
            ['Ejemplo - Usuario con 5 empresas:'],
            [''],
            ['Fila 1: Juan | Pérez | juan@mail.com | ... | Org1 | Super1 | Org2 | Super2 | Org3 | Super3'],
            ['Fila 2: Juan | Pérez | juan@mail.com | ... | Org4 | Super4 | Org5 | (vacío) | (vacío) | (vacío)'],
            ['→ Resultado: 1 usuario con 5 empresas'],
            [''],
            [''],
            ['✅ PASO 3: VALIDACIÓN'],
            [''],
            ['El sistema validará:'],
            ['• Email único (no duplicado en sistema)'],
            ['• Formato de email correcto'],
            ['• RUC existe en el sistema'],
            ['• Cada usuario tiene al menos una empresa, con al menos un rol en ella'],
            ['• Supervisor existe y pertenece a la empresa'],
            ['• Datos consistentes en usuarios con email duplicado'],
            [''],
            [''],
            ['⚠️ ERRORES COMUNES'],
            [''],
            ['1. "El email/documento ya existe en el sistema"'],
            ['   → Esta carga solo da de alta usuarios NUEVOS; no modifica los ya registrados'],
            ['   → Quita esa fila del archivo y vuelve a subirlo'],
            ['   → Si necesitas cambiarle algo a un usuario que ya existe, hazlo desde'],
            ['     el mantenimiento de usuarios, uno por uno'],
            [''],
            ['2. "RUC no encontrado"'],
            ['   → Usa SOLO RUCs de la hoja "Catálogo_Organizaciones"'],
            ['   → Selecciona de la lista desplegable'],
            [''],
            ['3. "Supervisor no pertenece a organización"'],
            ['   → Verifica en "Catálogo_Supervisores"'],
            ['   → Ese supervisor debe estar en esa empresa'],
            [''],
            ['4. "Datos inconsistentes"'],
            ['   → Si repites un usuario (mismo email), los datos deben coincidir'],
            ['   → Copia y pega para garantizar exactitud'],
            [''],
            [''],
            ['💡 CONSEJOS'],
            [''],
            ['• Usa COPIAR Y PEGAR para usuarios repetidos'],
            ['• Las listas desplegables previenen errores de tipeo'],
            ['• Revisa el "Catálogo_Organizaciones" primero'],
            ['• Máximo 1,000 usuarios por archivo'],
            ['• Guarda una copia antes de subir'],
            [''],
            [''],
            ['📞 SOPORTE'],
            [''],
            ['Si tienes problemas:'],
            ['1. Verifica que completaste todos los campos OBLIGATORIOS'],
            ['2. Usa las listas desplegables (no escribas manualmente)'],
            ['3. Consulta los catálogos de referencia'],
            ['4. Contacta al administrador del sistema'],
            [''],
            [''],
            ['¡Buena suerte! 🚀'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Título principal
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '5B9BD5'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Títulos de secciones (PASO 1, PASO 2, etc.)
        $sectionRows = [3, 18, 35, 50, 62];
        foreach ($sectionRows as $row) {
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '70AD47'],
                ],
            ]);
        }

        // Wrap text para todas las celdas
        $sheet->getStyle('A1:A100')->getAlignment()->setWrapText(true);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 100, // Ancho completo para instrucciones
        ];
    }
}
