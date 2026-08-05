<?php

namespace Database\Seeders;

use App\Models\DocumentBatch;
use App\Models\DocumentType;
use App\Models\Document;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Models\VacationRequest;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de DEMOSTRACIÓN para el paquete de entrega: deja la plataforma con
 * todos los módulos poblados y en todos los estados relevantes, para que se
 * pueda comprobar que la aplicación entregada funciona sin cargar ni un dato
 * real de ninguna empresa.
 *
 * Se apoya en los seeders base (roles, empresas, usuarios) y añade lo que a
 * ellos les falta para que la demo sea completa:
 *
 *   - labor_regime por empresa: sin él todas devengan 30 días y no se ve la
 *     diferencia entre régimen general y MYPE (15).
 *   - hire_date en user_tenants: es la BASE del cálculo de vacaciones. Sin
 *     fecha de ingreso, accrued y truncas son 0 y TODOS los saldos salen en
 *     cero, justo el módulo que el cliente quiere ver.
 *   - Usuarios admin_tenant y aprobador: los seeders base solo crean root,
 *     admin y client, así que no había forma de demostrar el flujo de
 *     aprobación de vacaciones ni la administración de empresas.
 *   - supervisor_id: sin él no hay subordinados y la bandeja de aprobación
 *     sale vacía.
 *   - Documentos y solicitudes de vacaciones en cada estado.
 *
 * Uso:  php artisan migrate:fresh --seed --seeder=Database\\Seeders\\DemoSeeder
 *
 * Todos los usuarios quedan con la contraseña 'password'.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TenantSeeder::class,
            UserSeeder::class,
            DocumentTypeSeeder::class,
        ]);

        $this->command->info('  Enriqueciendo datos base para la demo...');

        $this->configurarRegimenLaboral();
        $this->crearUsuariosDeRolesFaltantes();
        $this->configurarVinculosLaborales();
        $this->crearDocumentos();
        // Después de crearDocumentos: los lotes enlazan los documentos ya
        // creados en vez de duplicarlos, que es como llegan en la carga real.
        $this->crearLotesDeCarga();
        $this->crearSolicitudesDeVacaciones();

        $this->resumen();
    }

    /**
     * Régimen laboral distinto por empresa para que se vea el efecto en el
     * devengo: general = 30 días/año, MYPE (micro/pequeña) = 15.
     */
    private function configurarRegimenLaboral(): void
    {
        $regimenes = ['general', 'pequena', 'general'];

        Tenant::orderBy('id')->get()->each(function (Tenant $tenant, int $i) use ($regimenes) {
            $tenant->update([
                'labor_regime' => $regimenes[$i] ?? 'general',
                'initial_employee_count' => [12, 8, 3][$i] ?? 5,
            ]);
        });
    }

    /**
     * admin_tenant y aprobador: los dos roles que los seeders base no crean y
     * sin los cuales no se pueden demostrar ni la administración de empresas
     * ni el flujo de aprobación.
     */
    private function crearUsuariosDeRolesFaltantes(): void
    {
        $roles = Role::all()->keyBy('name');
        $tenants = Tenant::orderBy('id')->get();

        $adminTenant = $this->crearUsuario([
            'name' => 'Lucía',
            'last_name' => 'Vargas Chávez',
            'email' => 'admin.clientes@miboleta.demo',
            'document_text' => '10101010',
        ]);
        $adminTenant->roles()->attach($roles['admin_tenant'], ['granted_at' => now()]);
        $adminTenant->tenants()->attach($tenants[0]->id, ['is_primary' => true]);
        $this->rolEnEmpresa($adminTenant->id, $tenants[0]->id, $roles['admin_tenant']->id);

        $aprobador = $this->crearUsuario([
            'name' => 'Roberto',
            'last_name' => 'Salazar Núñez',
            'email' => 'aprobador@miboleta.demo',
            'document_text' => '20202020',
        ]);
        $aprobador->roles()->attach($roles['aprobador'], ['granted_at' => now()]);
        $aprobador->tenants()->attach($tenants[0]->id, ['is_primary' => true]);
        $this->rolEnEmpresa($aprobador->id, $tenants[0]->id, $roles['aprobador']->id);

        // Dos empleados más en la primera empresa para que la bandeja de
        // aprobación tenga varias filas y se aprecie el listado.
        foreach ([
            ['name' => 'Elena', 'last_name' => 'Quispe Mamani', 'email' => 'elena.quispe@miboleta.demo', 'document_text' => '30303030'],
            ['name' => 'Diego', 'last_name' => 'Ramos Flores', 'email' => 'diego.ramos@miboleta.demo', 'document_text' => '40404040'],
        ] as $datos) {
            $empleado = $this->crearUsuario($datos);
            $empleado->roles()->attach($roles['client'], ['granted_at' => now()]);
            $empleado->tenants()->attach($tenants[0]->id, ['is_primary' => true]);
            $this->rolEnEmpresa($empleado->id, $tenants[0]->id, $roles['client']->id);
        }
    }

    /**
     * hire_date, saldo inicial, área, cargo y supervisor en el pivote
     * user_tenants. Las fechas de ingreso se escalonan a propósito para que
     * los cuatro conceptos del cliente den cifras distintas y comprobables:
     * quien lleva 3 años tiene 3 devengos completos, quien lleva 7 meses solo
     * truncas, y quien acaba de entrar casi nada.
     */
    private function configurarVinculosLaborales(): void
    {
        $primerTenant = Tenant::orderBy('id')->first();
        $aprobador = User::where('email', 'aprobador@miboleta.demo')->first();

        $perfiles = [
            'aprobador@miboleta.demo'              => ['anios' => 5, 'meses' => 0, 'inicial' => 0,  'area' => 'Operaciones', 'cargo' => 'Jefe de Operaciones'],
            'admin.clientes@miboleta.demo'         => ['anios' => 4, 'meses' => 2, 'inicial' => 0,  'area' => 'Administración', 'cargo' => 'Administradora'],
            'juan.perez@corporacionabc.com'        => ['anios' => 3, 'meses' => 0, 'inicial' => 5,  'area' => 'Contabilidad', 'cargo' => 'Analista Contable'],
            'elena.quispe@miboleta.demo'           => ['anios' => 0, 'meses' => 7, 'inicial' => 0,  'area' => 'Ventas', 'cargo' => 'Ejecutiva Comercial'],
            'diego.ramos@miboleta.demo'            => ['anios' => 1, 'meses' => 3, 'inicial' => 12, 'area' => 'Sistemas', 'cargo' => 'Soporte Técnico'],
            'admin@corporacionabc.com'             => ['anios' => 6, 'meses' => 0, 'inicial' => 0,  'area' => 'Gerencia', 'cargo' => 'Gerente de RR.HH.'],
        ];

        foreach ($perfiles as $email => $p) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                continue;
            }

            $hireDate = now()->subYears($p['anios'])->subMonths($p['meses'])->startOfDay();

            // Los empleados reportan al aprobador; el aprobador no se
            // supervisa a sí mismo.
            $supervisorId = $user->id === $aprobador?->id ? null : $aprobador?->id;

            $user->tenants()->updateExistingPivot($primerTenant->id, [
                'hire_date' => $hireDate,
                'vacation_balance_initial' => $p['inicial'],
                'department' => $p['area'],
                'position' => $p['cargo'],
                'supervisor_id' => $supervisorId,
            ]);
        }

        // La segunda empresa también necesita fechas para no salir en cero.
        $segundoTenant = Tenant::orderBy('id')->skip(1)->first();
        foreach (['pedro.lopez@empresaxyz.com' => 2, 'admin@empresaxyz.com' => 4] as $email => $anios) {
            $user = User::where('email', $email)->first();
            if ($user && $segundoTenant) {
                $user->tenants()->updateExistingPivot($segundoTenant->id, [
                    'hire_date' => now()->subYears($anios)->startOfDay(),
                    'vacation_balance_initial' => 0,
                    'department' => 'Operaciones',
                    'position' => 'Colaborador',
                ]);
            }
        }
    }

    /**
     * Boletas de ejemplo en varios periodos y estados. Se escribe un PDF real
     * (mínimo pero válido) por documento: sin archivo, la descarga y la vista
     * previa fallarían y la demo no probaría nada.
     */
    private function crearDocumentos(): void
    {
        $tenant = Tenant::orderBy('id')->first();
        $boleta = DocumentType::where('name', 'boleta_remuneraciones')->first()
            ?? DocumentType::orderBy('id')->first();
        $subidoPor = User::where('email', 'admin@corporacionabc.com')->first();

        $empleados = User::whereIn('email', [
            'juan.perez@corporacionabc.com',
            'elena.quispe@miboleta.demo',
            'diego.ramos@miboleta.demo',
        ])->get();

        $periodos = ['2026-05', '2026-06', '2026-07'];

        foreach ($empleados as $empleado) {
            foreach ($periodos as $i => $periodo) {
                $ruta = "documents/demo/{$empleado->document_text}-{$periodo}.pdf";
                Storage::disk('local')->put($ruta, $this->pdfMinimo(
                    "Boleta de pago {$periodo} - {$empleado->full_name}"
                ));

                // El periodo más antiguo va firmado, el resto pendiente: así
                // se ve el estado "Firmado" y el flujo pendiente a la vez.
                $firmado = $i === 0;

                Document::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $empleado->id,
                    'doc_type_id' => $boleta?->id,
                    'employee_document_number' => $empleado->document_text,
                    'period' => $periodo,
                    'file_path' => $ruta,
                    'file_size' => strlen($this->pdfMinimo('x')),
                    'original_name' => "boleta-{$periodo}.pdf",
                    'status' => $firmado ? 'signed' : 'pending',
                    'uploaded_by' => $subidoPor?->id,
                    'requires_signature' => true,
                    'signed_at' => $firmado ? now()->subDays(20 - $i) : null,
                    'notified' => true,
                    'notified_at' => now()->subDays(25 - $i),
                    'version' => 1,
                ]);
            }
        }
    }

    /**
     * Lotes de carga, uno por periodo, con sus documentos enlazados.
     *
     * Sin esto la demo dejaba `document_batches` vacía, y las cuatro secciones
     * del manual que documentan el Historial de Lotes (7.1 a 7.4) no tenían
     * nada que enseñar: la lista salía vacía y al detalle no se podía ni
     * entrar. Se descubrió al automatizar las capturas, que fallaban con "la
     * tabla no tiene filas".
     *
     * Uno de los lotes queda en 'completed_with_errors', el único estado que
     * enseña el resumen de filas fallidas. OJO: no es 'partial'. Los dos
     * enums no coinciden y no son intercambiables:
     *
     *   document_batches: pending, processing, completed,
     *                     completed_with_errors, failed
     *   user_batches:     pending, processing, completed, failed, partial
     *
     * 'partial' es de la carga masiva de USUARIOS. Usarlo aquí revienta con
     * "Data truncated for column 'status'".
     */
    private function crearLotesDeCarga(): void
    {
        $tenant = Tenant::orderBy('id')->first();
        $boleta = DocumentType::where('name', 'boleta_remuneraciones')->first()
            ?? DocumentType::orderBy('id')->first();
        $subidoPor = User::where('email', 'admin@corporacionabc.com')->first();

        foreach (['2026-05', '2026-06', '2026-07'] as $i => $periodo) {
            $documentos = Document::where('tenant_id', $tenant->id)
                ->where('period', $periodo)
                ->get();

            if ($documentos->isEmpty()) {
                continue;
            }

            // El último periodo se deja incompleto a propósito, con un error
            // por fila como los que devuelve el import real.
            $conErrores = $i === 2;
            $conError = $conErrores ? 1 : 0;

            $lote = DocumentBatch::create([
                'tenant_id' => $tenant->id,
                'uploaded_by' => $subidoPor?->id,
                'type_id' => $boleta?->id,
                'period' => $periodo,
                'original_filename' => "boletas-{$periodo}.zip",
                'total_files' => $documentos->count() + $conError,
                'processed_files' => $documentos->count() + $conError,
                'success_count' => $documentos->count(),
                'replaced_count' => 0,
                'orphan_count' => 0,
                'error_count' => $conError,
                'errors' => $conError ? [[
                    'row_number' => $documentos->count() + 1,
                    'file' => 'boleta-99999999.pdf',
                    'error' => 'No existe ningún empleado con el DNI 99999999',
                ]] : null,
                'notify_employees' => true,
                'notifications_sent' => true,
                'requires_signature' => true,
                'status' => $conErrores ? 'completed_with_errors' : 'completed',
                'started_at' => now()->subDays(30 - ($i * 10)),
                'completed_at' => now()->subDays(30 - ($i * 10))->addMinutes(3),
            ]);

            Document::whereIn('id', $documentos->pluck('id'))->update(['batch_id' => $lote->id]);
        }
    }

    /**
     * Solicitudes en los cuatro estados, para que las tres pestañas de
     * "Vacaciones del Equipo" (Pendientes, Por Confirmar, Historial) tengan
     * contenido y el aprobador pueda decidir sobre algo real.
     */
    private function crearSolicitudesDeVacaciones(): void
    {
        $tenant = Tenant::orderBy('id')->first();
        $aprobador = User::where('email', 'aprobador@miboleta.demo')->first();

        $juan = User::where('email', 'juan.perez@corporacionabc.com')->first();
        $elena = User::where('email', 'elena.quispe@miboleta.demo')->first();
        $diego = User::where('email', 'diego.ramos@miboleta.demo')->first();

        // PENDIENTE de aprobación (pestaña "Pendientes").
        VacationRequest::create([
            'user_id' => $juan->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(34)->toDateString(),
            'days_requested' => 15,
            'reason' => 'Vacaciones familiares programadas',
            'status' => VacationRequest::STATUS_PENDING,
        ]);

        // PENDIENTE que EXCEDE el saldo: dispara el aviso ámbar de la bandeja.
        VacationRequest::create([
            'user_id' => $elena->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(39)->toDateString(),
            'days_requested' => 30,
            'reason' => 'Viaje al extranjero',
            'status' => VacationRequest::STATUS_PENDING,
        ]);

        // APROBADA y ya pasada, sin confirmar (pestaña "Por Confirmar").
        VacationRequest::create([
            'user_id' => $diego->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->subDays(24)->toDateString(),
            'days_requested' => 7,
            'reason' => 'Descanso',
            'status' => VacationRequest::STATUS_APPROVED,
            'approved_by' => $aprobador?->id,
            'approved_at' => now()->subDays(40),
        ]);

        // APROBADA y CONFIRMADA como tomada (cuenta como Gozadas).
        VacationRequest::create([
            'user_id' => $juan->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->subMonths(6)->addDays(9)->toDateString(),
            'days_requested' => 10,
            'reason' => 'Vacaciones de medio año',
            'status' => VacationRequest::STATUS_APPROVED,
            'approved_by' => $aprobador?->id,
            'approved_at' => now()->subMonths(6)->subDays(15),
            'was_taken' => true,
            'confirmed_by' => $aprobador?->id,
            'confirmed_at' => now()->subMonths(6)->addDays(12),
        ]);

        // RECHAZADA (pestaña "Historial").
        VacationRequest::create([
            'user_id' => $elena->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subMonths(2)->addDays(4)->toDateString(),
            'days_requested' => 5,
            'reason' => 'Asuntos personales',
            'status' => VacationRequest::STATUS_REJECTED,
            'rejected_by' => $aprobador?->id,
            'rejected_at' => now()->subMonths(2)->subDays(10),
            'rejection_reason' => 'Coincide con el cierre contable del mes',
        ]);
    }

    private function crearUsuario(array $datos): User
    {
        return User::create(array_merge([
            'password' => Hash::make('password'),
            'document_type' => 'dni',
            'phone' => '+51 999 000 000',
            'status' => 'active',
            'email_verified_at' => now(),
        ], $datos));
    }

    private function rolEnEmpresa(int $userId, int $tenantId, int $roleId): void
    {
        UserTenantRole::insert([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * PDF válido mínimo (un objeto por sección, sin fuentes embebidas). No
     * pretende ser una boleta real: solo un archivo que abra sin error para
     * que descarga y vista previa funcionen en la demo.
     */
    private function pdfMinimo(string $titulo): string
    {
        $texto = str_replace(['(', ')', '\\'], '', $titulo);
        $contenido = "BT /F1 12 Tf 60 760 Td ({$texto}) Tj ET";
        $len = strlen($contenido);

        return "%PDF-1.4\n"
            . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]"
            . "/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>endobj\n"
            . "4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
            . "5 0 obj<</Length {$len}>>stream\n{$contenido}\nendstream endobj\n"
            . "trailer<</Root 1 0 R/Size 6>>\n%%EOF\n";
    }

    private function resumen(): void
    {
        $this->command->newLine();
        $this->command->info('  Datos de demostración creados:');
        $this->command->line('    Empresas: ' . Tenant::count() . ' (una en régimen MYPE)');
        $this->command->line('    Usuarios: ' . User::count() . ' — contraseña: password');
        $this->command->line('    Documentos: ' . Document::count());
        $this->command->line('    Lotes de carga: ' . DocumentBatch::count() . ' (uno parcial)');
        $this->command->line('    Solicitudes de vacaciones: ' . VacationRequest::count());
        $this->command->newLine();
        $this->command->line('    root .............. admin@email.com');
        $this->command->line('    admin_tenant ...... admin.clientes@miboleta.demo');
        $this->command->line('    admin ............. admin@corporacionabc.com');
        $this->command->line('    aprobador ......... aprobador@miboleta.demo');
        $this->command->line('    empleado .......... juan.perez@corporacionabc.com');
    }
}
