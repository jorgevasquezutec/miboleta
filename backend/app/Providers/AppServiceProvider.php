<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAccessMatrixGates();
    }

    /**
     * Registra un Gate por cada ability de config/access_matrix.php (fuente
     * única de verdad de la Matriz de Accesos).
     *
     * Uso en controllers:  $this->authorize('audit.view', $tenantId);
     * Uso suelto:          $user->can('documents.delete', $document->tenant_id);
     *
     * El segundo argumento es el ID de la empresa en cuyo contexto se evalúa el
     * rol. Para acciones sobre un recurso concreto debe ser el tenant DEL
     * RECURSO ($document->tenant_id), no la empresa "activa" de la sesión:
     * autorizar con la activa permitiría actuar sobre la empresa B usando el rol
     * que el usuario tiene en la A. Para listados/dashboard (sin recurso puntual)
     * usar App\Services\ActiveTenantResolver.
     *
     * No se define un Gate::before para 'root': la matriz excluye a root de
     * varias abilities operativas, así que root se lista explícitamente donde
     * corresponde y se evalúa por el mismo camino que el resto.
     */
    private function registerAccessMatrixGates(): void
    {
        foreach (array_keys(config('access_matrix', [])) as $ability) {
            Gate::define(
                $ability,
                fn (User $user, ?int $tenantId = null) => $user->hasAbility($ability, $tenantId)
            );
        }
    }
}
