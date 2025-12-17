<?php

namespace App\Exports\Sheets;

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

    public function __construct(int $maxOrganizations)
    {
        $this->maxOrganizations = $maxOrganizations;
    }

    public function title(): string
    {
        return 'Instrucciones';
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
            ['• nombre: Nombre del usuario (ej: Juan)'],
            ['• apellido: Apellidos completos (ej: Pérez García)'],
            ['• email: Correo electrónico ÚNICO (ej: juan.perez@empresa.com)'],
            ['• tipo_documento: Selecciona de la lista desplegable (dni, ce, passport, ruc)'],
            ['• numero_documento: Número según el tipo (ej: 12345678)'],
            ['• rol: Selecciona de la lista (client, root, admin)'],
            ['• estado: Selecciona (active o inactive)'],
            [''],
            ['CAMPOS OPCIONALES:'],
            ['• telefono: Número de teléfono (ej: +51 999 999 999)'],
            [''],
            [''],
            ['🏢 PASO 2: ASIGNAR ORGANIZACIONES'],
            [''],
            ["Este template está configurado para hasta {$this->maxOrganizations} organización(es) por usuario."],
            [''],
            ['Para cada organización:'],
            ['1. org{N}_ruc: Selecciona el RUC de la lista desplegable'],
            ['2. org{N}_supervisor_email: (Opcional) Email del supervisor en esa empresa'],
            [''],
            ['IMPORTANTE:'],
            ['• Si un usuario pertenece a MÁS organizaciones, repite el usuario en otra fila'],
            ['• Los datos básicos (nombre, email, etc.) deben ser EXACTAMENTE iguales'],
            ['• El sistema consolidará automáticamente las organizaciones del mismo email'],
            [''],
            ['Ejemplo - Usuario con 5 organizaciones:'],
            [''],
            ['Fila 1: Juan | Pérez | juan@mail.com | ... | Org1 | Super1 | Org2 | Super2 | Org3 | Super3'],
            ['Fila 2: Juan | Pérez | juan@mail.com | ... | Org4 | Super4 | Org5 | (vacío) | (vacío) | (vacío)'],
            ['→ Resultado: 1 usuario con 5 organizaciones'],
            [''],
            [''],
            ['✅ PASO 3: VALIDACIÓN'],
            [''],
            ['El sistema validará:'],
            ['• Email único (no duplicado en sistema)'],
            ['• Formato de email correcto'],
            ['• RUC existe en el sistema'],
            ['• Supervisor existe y pertenece a la organización'],
            ['• Datos consistentes en usuarios con email duplicado'],
            [''],
            [''],
            ['⚠️ ERRORES COMUNES'],
            [''],
            ['1. "Email duplicado"'],
            ['   → Verifica que no existe en el sistema'],
            ['   → O activa "Actualizar usuarios existentes"'],
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
