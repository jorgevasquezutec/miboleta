<?php

namespace App\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Models\AuditLog;
use App\Models\AuditSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mantenedor de auditoría (solo root): permite ver el catálogo de acciones y
 * activar/desactivar la captura de cada una en runtime. Las acciones
 * AuditLog::ALWAYS_ON no son desactivables. Mismo patrón de enforcement que
 * PlatformSettingsService::ensureRoot().
 */
class AuditSettingsService
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * Catálogo completo de acciones para el mantenedor: cada acción con su
     * etiqueta, categoría, si está bloqueada (always-on), si está activa y
     * cuántos registros existen de ese tipo.
     *
     * @return array<int, array<string, mixed>>
     * @throws UnauthorizedAccessException
     */
    public function getCatalog(User $user): array
    {
        $this->ensureRoot($user);

        $disabled = AuditSettings::current()->disabled_actions ?? [];
        $counts = $this->countsByAction();

        $catalog = [];
        foreach (AuditLog::allActions() as $action) {
            $locked = AuditLog::isAlwaysOn($action);
            $catalog[] = [
                'action' => $action,
                'label' => (new AuditLog(['action' => $action]))->description,
                'category' => explode('.', $action)[0] ?? 'other',
                'locked' => $locked,
                // Las always-on siempre están activas; el resto según el set.
                'enabled' => $locked ? true : !in_array($action, $disabled, true),
                'count' => (int) ($counts[$action] ?? 0),
            ];
        }

        return $catalog;
    }

    /**
     * Actualiza el conjunto de acciones desactivadas. Filtra (rechaza) las
     * always-on y cualquier acción desconocida: solo se aceptan acciones
     * toggleables. Invalida el cache y registra el meta-evento always-on.
     *
     * @param array<int, string> $disabledActions
     * @throws UnauthorizedAccessException
     */
    public function updateSettings(User $user, array $disabledActions): AuditSettings
    {
        $this->ensureRoot($user);

        $toggleable = AuditLog::toggleableActions();
        // Solo acciones toggleables conocidas (descarta always-on y basura).
        $clean = array_values(array_unique(array_filter(
            $disabledActions,
            fn ($a) => is_string($a) && in_array($a, $toggleable, true)
        )));

        $settings = AuditSettings::current();
        $old = $settings->disabled_actions ?? [];

        $settings->fill([
            'disabled_actions' => $clean,
            'updated_by' => $user->id,
        ]);
        $settings->save();

        // Invalidar el cache leído en cada AuditService::log().
        cache()->forget(AuditService::DISABLED_ACTIONS_CACHE_KEY);

        // Meta-evento always-on: quién cambió qué.
        $this->auditService->logAuditSettingsUpdated($old, $clean);

        Log::info('[AuditSettingsService] configuración de auditoría actualizada', [
            'user_id' => $user->id,
            'disabled_count' => count($clean),
        ]);

        return $settings->fresh();
    }

    /**
     * Conteo de registros de auditoría por acción.
     *
     * @return array<string, int>
     */
    protected function countsByAction(): array
    {
        return AuditLog::select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action')
            ->map(fn ($c) => (int) $c)
            ->toArray();
    }

    protected function ensureRoot(User $user): void
    {
        if (!$user->isRoot()) {
            throw new UnauthorizedAccessException(
                'No autorizado. Solo el administrador de plataforma puede gestionar la configuración de auditoría.'
            );
        }
    }
}
