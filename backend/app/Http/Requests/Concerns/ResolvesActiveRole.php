<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Services\ActiveTenantResolver;

/**
 * Resuelve, dentro de un FormRequest, el rol EFECTIVO del usuario en la empresa
 * activa de la petición.
 *
 * Reemplaza el patrón `$this->user()->getCurrentRole()` que usaban todos los
 * authorize(): ese método, sin empresa, cae al respaldo global `user_roles` (la
 * UNIÓN de los roles del usuario en TODAS sus empresas, resuelto con ->first()
 * sin ORDER BY). Con él, ser admin en una empresa concedía permisos operando
 * sobre otra donde el usuario solo es client.
 *
 * La empresa activa sale del header X-Tenant-Ids, ya validado por el middleware
 * TenantFilter (que corre antes de la validación del FormRequest).
 */
trait ResolvesActiveRole
{
    /**
     * ¿El usuario tiene la ability en la empresa activa de la petición?
     *
     * Es la forma correcta de escribir un authorize(): consulta el Gate, que se
     * registra desde config/access_matrix.php (fuente única de verdad). NO
     * escribir listas de roles a mano aquí —p. ej.
     * `in_array($this->activeRole(), ['root','admin'])`—: esa duplicación es
     * justo lo que hizo derivar los permisos del backend respecto de la matriz
     * y del frontend (el bug de "menú visible -> 403").
     *
     * Fail-closed: sin usuario, sin rol en la empresa activa o con una ability
     * inexistente, devuelve false.
     */
    protected function allowsAbility(string $ability): bool
    {
        $user = $this->user();

        if (!$user instanceof User) {
            return false;
        }

        return $user->can($ability, $this->activeTenantId());
    }

    /**
     * Empresa activa de la petición (header X-Tenant-Ids, ya validado por el
     * middleware TenantFilter, que corre antes del FormRequest).
     */
    protected function activeTenantId(): ?int
    {
        $user = $this->user();

        if (!$user instanceof User) {
            return null;
        }

        return app(ActiveTenantResolver::class)->resolve($this, $user);
    }

    /**
     * Rol del usuario en la empresa activa, o null si no tiene ninguno allí.
     *
     * Solo para reglas que dependen del NOMBRE del rol (p. ej. validación de
     * payload: "un admin_tenant solo puede asignar aprobador/client"). Para
     * decidir permisos usar allowsAbility().
     */
    protected function activeRole(): ?string
    {
        $user = $this->user();

        if (!$user instanceof User) {
            return null;
        }

        return User::roleForTenant($user, $this->activeTenantId());
    }
}
