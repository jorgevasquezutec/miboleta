<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

/**
 * Borra TODOS los datos de negocio de una instalación (empresas, usuarios no
 * root, documentos, vacaciones, notificaciones, auditoría, etc.) dejando solo
 * la(s) cuenta(s) root indicada(s). Pensado para dejar limpio un ambiente de
 * entrega/demo antes de pasarlo a producción real, o para "resetear" una
 * instalación de prueba sin reinstalarla desde cero.
 *
 *   php artisan miboleta:limpiar-datos                      # dry-run (no borra nada)
 *   php artisan miboleta:limpiar-datos --ejecutar            # pide confirmación por teclado
 *   php artisan miboleta:limpiar-datos --ejecutar --confirmar="BORRAR miboleta@midominio.com"
 *   php artisan miboleta:limpiar-datos --ejecutar --mantener-id=1
 *   php artisan miboleta:limpiar-datos --ejecutar --mantener-root=admin@empresa.com
 *
 * Decisiones (ver plan de limpieza de datos):
 *  - Por defecto se conservan TODOS los usuarios root activos (no
 *    eliminados). --mantener-id=* y --mantener-root=* (repetibles, mutuamente
 *    excluyentes) restringen la conservación a un subconjunto puntual; cada
 *    id/email debe ser un root activo o el comando aborta SIN TOCAR NADA.
 *  - audit_logs se borra salvo --mantener-auditoria.
 *  - Se reinicia el AUTO_INCREMENT (mysql) de las tablas que quedan
 *    completamente vacías, salvo --sin-reiniciar-ids.
 *  - storage/logs se conserva salvo --borrar-logs.
 *  - El certificado de firma (disco "certificates") y las filas de
 *    signature_settings/platform_settings/audit_settings/roles/document_types
 *    NUNCA se tocan (son catálogo/configuración, no datos de negocio).
 *
 * Es DESTRUCTIVO e IRREVERSIBLE: exige --ejecutar (si no, solo simula) y,
 * dentro de --ejecutar, una confirmación escrita exacta con el nombre de la
 * base de datos y el host de la instalación (para evitar pegar el comando en
 * la terminal equivocada). En producción exige además --force.
 */
class LimpiarDatos extends Command
{
    protected $signature = 'miboleta:limpiar-datos
                            {--ejecutar : Aplica el borrado (sin esto, solo se simula y no se escribe nada)}
                            {--confirmar= : Frase de confirmación exacta, para uso no interactivo}
                            {--force : Requerido además de --confirmar/confirmación en entornos de producción}
                            {--mantener-id=* : ID de usuario a conservar (repetible). Debe ser root activo. No combinable con --mantener-root}
                            {--mantener-root=* : Email de un root a conservar (repetible). Por defecto se conservan TODOS los root activos}
                            {--mantener-auditoria : No borra audit_logs}
                            {--borrar-logs : También vacía storage/logs}
                            {--sin-reiniciar-ids : No reinicia el AUTO_INCREMENT tras borrar (solo aplica a mysql)}';

    protected $description = 'Borra todos los datos de negocio de la plataforma, dejando solo root(s). Por defecto es un dry-run.';

    /**
     * Tablas de negocio que se borran POR COMPLETO (todas sus filas, incluidas
     * las soft-deleted), en orden compatible con las FK de la BD: cada tabla
     * de esta lista se vacía antes que cualquier tabla de la que dependa
     * (documents.doc_type_id/uploaded_by son RESTRICT, así que documents debe
     * quedar vacía antes de borrar usuarios; el resto de relaciones de esta
     * lista son CASCADE/SET NULL, pero se vacían explícitamente igual para
     * no depender de eso y para poder reportar un conteo exacto por tabla).
     * tenants va al final de este grupo porque casi todo lo de arriba cuelga
     * de una empresa.
     *
     * @var list<string>
     */
    private const TABLAS_NEGOCIO = [
        'document_signature_codes',
        'documents',
        'document_batches',
        'vacation_requests',
        'notifications',
        'user_batches',
        'user_tenant_roles',
        'user_tenants',
        'tenants',
    ];

    /**
     * Sesiones/tokens de TODOS los usuarios (incluidos los root conservados:
     * deben volver a iniciar sesión). Sin relación entre sí, se pueden borrar
     * en cualquier orden.
     *
     * @var list<string>
     */
    private const TABLAS_SESIONES_TOKENS = [
        'sessions',
        'personal_access_tokens',
        'refresh_tokens',
        'password_reset_tokens',
    ];

    /**
     * Infraestructura de colas/caché. No tiene relación con usuarios/tenants;
     * se limpia al final por prolijidad, no por necesidad de orden.
     *
     * @var list<string>
     */
    private const TABLAS_INFRA = [
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
    ];

    public function handle(): int
    {
        $usuariosAConservar = $this->resolverUsuariosAConservar();

        if ($usuariosAConservar === null) {
            // El error ya se imprimió en el resolver. Nada se tocó todavía.
            return self::FAILURE;
        }

        if ($usuariosAConservar->isEmpty()) {
            $this->error('No quedaría ningún usuario root. Se aborta: la plataforma nunca debe quedar sin acceso.');

            return self::FAILURE;
        }

        $this->mostrarResumen($usuariosAConservar);

        if (! $this->option('ejecutar')) {
            $this->newLine();
            $this->comment('Simulación (dry-run): no se modificó nada. Vuelve a ejecutar con --ejecutar para aplicar el borrado.');

            return self::SUCCESS;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('En producción se requiere además la opción --force.');

            return self::FAILURE;
        }

        if (! $this->confirmacionValida()) {
            $this->error('Confirmación incorrecta o ausente. No se modificó nada.');

            return self::FAILURE;
        }

        $idsAConservar = $usuariosAConservar->pluck('id')->all();

        $conteos = $this->ejecutarBorrado($idsAConservar);

        if (DB::connection()->getDriverName() === 'mysql' && ! $this->option('sin-reiniciar-ids')) {
            $this->reiniciarAutoIncrement();
        }

        $this->vaciarArchivos($usuariosAConservar);
        $this->limpiarColasYCache();
        $this->registrarAuditoria($usuariosAConservar, $conteos);
        $this->mostrarResumenFinal($usuariosAConservar, $conteos);

        return self::SUCCESS;
    }

    // ============ Resolución de qué root(s) se conservan ============

    /**
     * Devuelve la lista de usuarios a conservar (id + email), o null si algo
     * en las opciones es inválido (y ya se imprimió el error). Nunca toca la
     * BD para escribir: solo lee.
     *
     * @return Collection<int, object{id: int, email: string}>|null
     */
    private function resolverUsuariosAConservar(): ?Collection
    {
        $mantenerIds = array_values(array_filter(array_map('trim', (array) $this->option('mantener-id')), fn ($v) => $v !== ''));
        $mantenerEmails = array_values(array_filter(array_map('trim', (array) $this->option('mantener-root')), fn ($v) => $v !== ''));

        if ($mantenerIds !== [] && $mantenerEmails !== []) {
            $this->error('No se pueden combinar --mantener-id y --mantener-root: usa solo una de las dos.');

            return null;
        }

        if ($mantenerIds !== []) {
            return $this->resolverPorId($mantenerIds);
        }

        if ($mantenerEmails !== []) {
            return $this->resolverPorEmail($mantenerEmails);
        }

        return $this->rootsActivos();
    }

    /**
     * Todos los usuarios activos (no soft-deleted) con rol root. Comportamiento
     * por defecto, sin --mantener-id ni --mantener-root.
     *
     * @return Collection<int, object{id: int, email: string}>
     */
    private function rootsActivos(): Collection
    {
        return DB::table('users')
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.name', 'root')
            ->whereNull('users.deleted_at')
            ->where('users.status', 'active')
            ->select('users.id', 'users.email')
            ->distinct()
            ->orderBy('users.id')
            ->get();
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, object{id: int, email: string}>|null
     */
    private function resolverPorId(array $ids): ?Collection
    {
        $resultado = collect();

        foreach ($ids as $idCrudo) {
            $id = (int) $idCrudo;
            $usuario = DB::table('users')->where('id', $id)->first();

            if (! $usuario) {
                $this->error("--mantener-id={$idCrudo}: no existe ningún usuario con ese id.");

                return null;
            }

            if (! $this->esActivoYRoot($usuario)) {
                $this->error("--mantener-id={$idCrudo} ({$usuario->email}): no está activo o no tiene el rol root.");

                return null;
            }

            $resultado->push((object) ['id' => $usuario->id, 'email' => $usuario->email]);
        }

        return $resultado;
    }

    /**
     * @param  list<string>  $emails
     * @return Collection<int, object{id: int, email: string}>|null
     */
    private function resolverPorEmail(array $emails): ?Collection
    {
        $resultado = collect();

        foreach ($emails as $email) {
            $usuario = DB::table('users')->where('email', $email)->first();

            if (! $usuario) {
                $this->error("--mantener-root={$email}: no existe ningún usuario con ese correo.");

                return null;
            }

            if (! $this->esActivoYRoot($usuario)) {
                $this->error("--mantener-root={$email}: no está activo o no tiene el rol root.");

                return null;
            }

            $resultado->push((object) ['id' => $usuario->id, 'email' => $usuario->email]);
        }

        return $resultado;
    }

    private function esActivoYRoot(object $usuario): bool
    {
        if ($usuario->deleted_at !== null || $usuario->status !== 'active') {
            return false;
        }

        return DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $usuario->id)
            ->where('roles.name', 'root')
            ->exists();
    }

    // ============ Confirmación ============

    private function confirmacionValida(): bool
    {
        $esperada = $this->fraseConfirmacion();
        $recibida = $this->option('confirmar');

        if ($recibida === null) {
            if (! $this->input->isInteractive()) {
                // No interactivo y sin --confirmar: no hay forma segura de
                // confirmar a ciegas.
                return false;
            }

            $recibida = $this->ask("Escribe exactamente «{$esperada}» para continuar");
        }

        return $recibida === $esperada;
    }

    private function fraseConfirmacion(): string
    {
        $conexion = config('database.default');
        $baseDatos = (string) config("database.connections.{$conexion}.database");
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: (string) config('app.url');

        return "BORRAR {$baseDatos}@{$host}";
    }

    // ============ Dry-run / resumen ============

    private function mostrarResumen(Collection $usuariosAConservar): void
    {
        $idsAConservar = $usuariosAConservar->pluck('id')->all();

        $this->info($this->option('ejecutar')
            ? '=== Se va a ejecutar el borrado ==='
            : '=== Simulación (dry-run). Nada se modifica todavía ===');
        $this->newLine();

        $filas = [];
        foreach (self::TABLAS_NEGOCIO as $tabla) {
            $filas[] = [$tabla, DB::table($tabla)->count()];
        }
        $filas[] = ['user_roles (de usuarios no conservados)', DB::table('user_roles')->whereNotIn('user_id', $idsAConservar)->count()];
        $filas[] = ['users (no conservados)', DB::table('users')->whereNotIn('id', $idsAConservar)->count()];
        foreach (self::TABLAS_SESIONES_TOKENS as $tabla) {
            $filas[] = [$tabla, DB::table($tabla)->count()];
        }
        if (! $this->option('mantener-auditoria')) {
            $filas[] = ['audit_logs', DB::table('audit_logs')->count()];
        }
        foreach (self::TABLAS_INFRA as $tabla) {
            $filas[] = [$tabla, DB::table($tabla)->count()];
        }

        $this->table(['Tabla', 'Filas a borrar'], $filas);

        $this->newLine();
        $this->info('Archivos a borrar:');
        $archivos = $this->contarArchivos($usuariosAConservar);
        $this->table(['Disco', 'Archivos'], collect($archivos)->map(fn ($n, $disco) => [$disco, $n])->values()->all());

        $this->newLine();
        $this->info('Colas (aproximado):');
        $this->line('  jobs (BD): '.DB::table('jobs')->count());
        $this->line('  failed_jobs (BD): '.DB::table('failed_jobs')->count());
        $this->mostrarTamanoColasRedis();

        $this->newLine();
        $this->info('Usuario(s) root que se conservan:');
        $this->table(['ID', 'Email'], $usuariosAConservar->map(fn ($u) => [$u->id, $u->email])->all());
    }

    private function mostrarTamanoColasRedis(): void
    {
        try {
            foreach ($this->colasConfiguradas() as $cola) {
                $this->line("  redis:{$cola}: ".Redis::connection()->llen("queues:{$cola}"));
            }
        } catch (\Throwable $e) {
            $this->line('  (Redis no disponible para medir colas: '.$e->getMessage().')');
        }
    }

    /**
     * @return array<string, int>
     */
    private function contarArchivos(Collection $usuariosAConservar): array
    {
        // Mismo criterio que vaciarDisco(): el .gitignore de la raíz se queda.
        $aBorrar = fn (string $disco) => collect(Storage::disk($disco)->allFiles())
            ->reject(fn ($archivo) => $archivo === '.gitignore')
            ->count();

        $conteos = ['documents' => $aBorrar('documents')];

        foreach ($this->discosPrivadosAVaciar() as $disco) {
            $conteos[$disco] = $aBorrar($disco);
        }

        $conteos['public (avatars + logos de tenants)'] = $this->contarArchivosPublicos($usuariosAConservar);

        return $conteos;
    }

    /**
     * "local" y "private" apuntan LITERALMENTE a la misma carpeta en
     * config/filesystems.php (storage_path('app/private')): son el mismo
     * disco con dos nombres históricos. Vaciarlo dos veces sobre la carpeta
     * real no duplicaría el conteo (la segunda pasada no encontraría nada),
     * pero sí es trabajo de más y una fuente de confusión en el reporte, así
     * que si comparten raíz física se procesa una sola vez. En tests, cada
     * Storage::fake('local')/Storage::fake('private') crea una raíz FALSA
     * distinta por nombre de disco, así que ahí sí se procesan ambos.
     *
     * @return list<string>
     */
    private function discosPrivadosAVaciar(): array
    {
        $rutaLocal = rtrim(Storage::disk('local')->path(''), '/');
        $rutaPrivada = rtrim(Storage::disk('private')->path(''), '/');

        return $rutaLocal === $rutaPrivada ? ['private'] : ['local', 'private'];
    }

    /**
     * Rutas (tal como están guardadas en users.avatar_url, sin pasar por el
     * accessor que las convierte en URL absoluta) de los avatares de los
     * usuarios que se conservan. Esos archivos NO se borran del disco
     * "public".
     *
     * @return list<string>
     */
    private function avatarsAConservar(Collection $usuariosAConservar): array
    {
        return DB::table('users')
            ->whereIn('id', $usuariosAConservar->pluck('id'))
            ->whereNotNull('avatar_url')
            ->pluck('avatar_url')
            ->all();
    }

    private function contarArchivosPublicos(Collection $usuariosAConservar): int
    {
        $aConservar = $this->avatarsAConservar($usuariosAConservar);
        $disco = Storage::disk('public');

        $avatares = collect($disco->allFiles('avatars'))
            ->reject(fn ($archivo) => in_array($archivo, $aConservar, true))
            ->count();

        $logos = count($disco->allFiles('tenants/logos'));

        return $avatares + $logos;
    }

    // ============ Borrado ============

    /**
     * @param  list<int>  $idsAConservar
     * @return array<string, int>
     */
    private function ejecutarBorrado(array $idsAConservar): array
    {
        $conteos = [];

        DB::transaction(function () use ($idsAConservar, &$conteos) {
            foreach (self::TABLAS_NEGOCIO as $tabla) {
                $conteos[$tabla] = DB::table($tabla)->delete();
            }

            // Se conservan las filas de user_roles de los usuarios que se
            // mantienen (su asignación al rol root). Si quien se la otorgó
            // (granted_by) es uno de los usuarios que se borran más abajo, la
            // FK "granted_by" (onDelete: set null) la deja en NULL sola en
            // cuanto ese usuario se borre — no hace falta un UPDATE explícito
            // aquí, y por eso el borrado de usuarios va DESPUÉS de este paso.
            $conteos['user_roles'] = DB::table('user_roles')->whereNotIn('user_id', $idsAConservar)->delete();

            foreach (self::TABLAS_SESIONES_TOKENS as $tabla) {
                $conteos[$tabla] = DB::table($tabla)->delete();
            }

            if (! $this->option('mantener-auditoria')) {
                $conteos['audit_logs'] = DB::table('audit_logs')->delete();
            }

            // documents (RESTRICT en doc_type_id/uploaded_by) ya está vacía
            // (ver TABLAS_NEGOCIO arriba), así que borrar usuarios aquí no
            // puede violar esa FK. Las referencias nullOnDelete que aún
            // sobrevivan (signature_settings.uploaded_by, platform_settings.
            // updated_by, audit_settings.updated_by, user_roles.granted_by
            // del root conservado) las pone en NULL el motor de BD al
            // ejecutar este DELETE, sin necesidad de tocarlas a mano.
            $conteos['users'] = DB::table('users')->whereNotIn('id', $idsAConservar)->delete();

            foreach (self::TABLAS_INFRA as $tabla) {
                $conteos[$tabla] = DB::table($tabla)->delete();
            }
        });

        return $conteos;
    }

    /**
     * Tablas que, tras ejecutarBorrado(), quedan COMPLETAMENTE vacías (a
     * diferencia de users/user_roles, que conservan filas del root). Solo
     * tiene sentido reiniciar el AUTO_INCREMENT de estas.
     */
    private function reiniciarAutoIncrement(): void
    {
        $tablas = [...self::TABLAS_NEGOCIO, 'personal_access_tokens', 'refresh_tokens', 'audit_logs', 'jobs', 'failed_jobs'];

        if ($this->option('mantener-auditoria')) {
            $tablas = array_diff($tablas, ['audit_logs']);
        }

        foreach ($tablas as $tabla) {
            DB::statement("ALTER TABLE `{$tabla}` AUTO_INCREMENT = 1");
        }
    }

    // ============ Archivos ============

    private function vaciarArchivos(Collection $usuariosAConservar): void
    {
        $this->info('Vaciando archivos...');

        $this->vaciarDisco('documents');

        foreach ($this->discosPrivadosAVaciar() as $disco) {
            $this->vaciarDisco($disco);
        }

        $this->vaciarLogosYAvataresPublicos($usuariosAConservar);

        // El certificado de firma digital (disco "certificates") NUNCA se
        // toca: no aparece en ningún lado de este método a propósito.

        if ($this->option('borrar-logs')) {
            $this->vaciarLogs();
        }
    }

    private function vaciarDisco(string $disco): void
    {
        $filesystem = Storage::disk($disco);

        foreach ($filesystem->directories() as $directorio) {
            $filesystem->deleteDirectory($directorio);
        }

        // El .gitignore de la raíz del disco es parte del repositorio (en
        // local, backend/storage está montado tal cual): borrarlo ensucia
        // el árbol de git. No es un dato, así que se deja.
        foreach ($filesystem->files() as $archivo) {
            if (basename($archivo) !== '.gitignore') {
                $filesystem->delete($archivo);
            }
        }
    }

    private function vaciarLogosYAvataresPublicos(Collection $usuariosAConservar): void
    {
        $aConservar = $this->avatarsAConservar($usuariosAConservar);
        $disco = Storage::disk('public');

        foreach ($disco->allFiles('avatars') as $archivo) {
            if (! in_array($archivo, $aConservar, true)) {
                $disco->delete($archivo);
            }
        }

        foreach ($disco->allFiles('tenants/logos') as $archivo) {
            $disco->delete($archivo);
        }
    }

    private function vaciarLogs(): void
    {
        if (! File::isDirectory(storage_path('logs'))) {
            return;
        }

        foreach (File::files(storage_path('logs')) as $archivo) {
            File::delete($archivo->getPathname());
        }
    }

    // ============ Colas / caché ============

    private function limpiarColasYCache(): void
    {
        $this->info('Limpiando colas y caché...');

        // --force en ambos: queue:clear y horizon:clear piden confirmación
        // cuando APP_ENV=production y, llamados vía Artisan::call (sin
        // terminal), se cancelarían en silencio dejando las colas intactas.
        foreach ($this->colasConfiguradas() as $cola) {
            try {
                Artisan::call('queue:clear', [
                    'connection' => 'redis',
                    '--queue' => $cola,
                    '--force' => true,
                ]);
            } catch (\Throwable $e) {
                $this->warn("No se pudo limpiar la cola '{$cola}': {$e->getMessage()}");
            }

            try {
                // horizon:clear sin --queue solo vacía la cola "default".
                // No se llama a horizon:forget: opera sobre un ID de job
                // fallido puntual y failed_jobs ya se vació en
                // ejecutarBorrado().
                Artisan::call('horizon:clear', ['--queue' => $cola, '--force' => true]);
            } catch (\Throwable $e) {
                $this->warn("No se pudo ejecutar horizon:clear en '{$cola}': {$e->getMessage()}");
            }
        }

        try {
            Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            $this->warn('No se pudo ejecutar cache:clear: '.$e->getMessage());
        }
    }

    /**
     * Nombres de cola declarados en los supervisors de Horizon (config/
     * horizon.php), sin duplicados. Fuente única: si mañana se agrega una
     * cola nueva ahí, este comando la recoge solo.
     *
     * @return list<string>
     */
    private function colasConfiguradas(): array
    {
        $colas = [];

        foreach (config('horizon.defaults', []) as $supervisor) {
            foreach ((array) ($supervisor['queue'] ?? []) as $cola) {
                $colas[] = $cola;
            }
        }

        return array_values(array_unique($colas));
    }

    // ============ Auditoría / resumen final ============

    /**
     * @param  array<string, int>  $conteos
     */
    private function registrarAuditoria(Collection $usuariosAConservar, array $conteos): void
    {
        // Se escribe DESPUÉS del commit (aquí ya hay tabla audit_logs otra
        // vez, esté vacía o conservada con --mantener-auditoria). No hay
        // usuario autenticado en un comando de consola: AuditService::log()
        // ya contempla Auth::user() === null y deja user_id/tenant_id en
        // NULL, que es lo correcto para una acción de mantenimiento de
        // plataforma sin actor HTTP.
        app(AuditService::class)->log(
            action: AuditLog::ACTION_PLATFORM_DATA_WIPED,
            entityType: 'Platform',
            metadata: [
                'usuarios_conservados' => $usuariosAConservar->map(fn ($u) => ['id' => $u->id, 'email' => $u->email])->all(),
                'filas_borradas' => $conteos,
                'auditoria_conservada' => (bool) $this->option('mantener-auditoria'),
                'logs_borrados' => (bool) $this->option('borrar-logs'),
            ],
        );
    }

    /**
     * @param  array<string, int>  $conteos
     */
    private function mostrarResumenFinal(Collection $usuariosAConservar, array $conteos): void
    {
        $this->newLine();
        $this->info('Listo. Filas borradas por tabla:');
        $this->table(['Tabla', 'Filas borradas'], collect($conteos)->map(fn ($n, $t) => [$t, $n])->values()->all());

        $this->newLine();
        $this->info('Quedan '.$usuariosAConservar->count().' usuario(s) root:');
        $this->table(['ID', 'Email'], $usuariosAConservar->map(fn ($u) => [$u->id, $u->email])->all());
    }
}
