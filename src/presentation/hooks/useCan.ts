import { useEffect } from 'react';
import { useAuthStore } from '@/presentation/stores/authStore';

/**
 * Gating de UI contra la Matriz de Accesos (fuente única de verdad, servida por
 * el backend desde config/access_matrix.php y guardada en authStore.accessMatrix).
 *
 * Evalúa la ability contra `currentRole` — el rol ACTIVO en la empresa activa —,
 * nunca contra `user.role`, que es un respaldo global legado (la UNIÓN de los
 * roles del usuario en todas sus empresas) y es más permisivo de lo real.
 *
 * Usar esto en vez de comparar nombres de rol a mano: el backend autoriza con
 * exactamente el mismo mapa, así que menús, rutas y botones no pueden volver a
 * desalinearse del backend (el bug de "menú visible -> 403").
 *
 * @example
 *   const canEdit = useCan('users.update');
 *   {canEdit && <Button>Editar</Button>}
 */

function evaluate(
  accessMatrix: Record<string, string[]> | null,
  currentRole: string | null,
  ability: string
): boolean {
  const allowed = accessMatrix?.[ability];

  if (!allowed) {
    // Ability inexistente en la matriz: fail-closed y avisar (probable typo o
    // desalineación con el backend).
    console.warn(`[can] Ability desconocida: "${ability}"`);
    return false;
  }

  return currentRole !== null && allowed.includes(currentRole);
}

/**
 * Versión reactiva: re-renderiza al cambiar de empresa o rol.
 */
export function useCan(ability: string): boolean {
  return useAuthStore((s) => evaluate(s.accessMatrix, s.currentRole, ability));
}

/**
 * ¿Tiene AL MENOS UNA de las abilities? Útil para entradas de menú o rutas que
 * agrupan varias acciones (p. ej. "Vacaciones" = solicitar o ver las propias).
 */
export function useCanAny(abilities: string[]): boolean {
  return useAuthStore((s) =>
    abilities.some((ability) => evaluate(s.accessMatrix, s.currentRole, ability))
  );
}

/**
 * ¿Ya tenemos la Matriz de Accesos para poder decidir?
 *
 * Mientras sea `null` NO se puede gatear: `evaluate()` denegaría todo, y quien
 * deniega redirige, y quien recibe el redirect vuelve a denegar — el bucle
 * infinito de "Maximum update depth exceeded". Con la matriz desconocida hay
 * que ESPERAR, no denegar.
 *
 * De paso la refresca UNA VEZ por carga de la app, no solo cuando falta: la
 * copia persistida en localStorage es una caché para poder pintar de inmediato
 * (y no denegarlo todo mientras llega la de red), pero la fuente de verdad es
 * el backend. Antes esto solo refetcheaba `if (!accessMatrix)`, así que una
 * copia vieja-pero-presente no se actualizaba nunca: al agregar una ability a
 * config/access_matrix.php, quien tuviera sesión abierta no la tenía en su
 * mapa, `evaluate()` la denegaba por fail-closed y el ítem de menú
 * correspondiente desaparecía hasta desloguearse.
 *
 * Autorizar sigue siendo cosa del backend: esto solo decide qué se pinta.
 */
export function useAccessMatrixReady(): boolean {
  const accessMatrix = useAuthStore((s) => s.accessMatrix);
  const user = useAuthStore((s) => s.user);
  const refreshed = useAuthStore((s) => s.accessMatrixRefreshed);
  const fetchAccessMatrix = useAuthStore((s) => s.fetchAccessMatrix);

  useEffect(() => {
    // `refreshed` no se persiste: arranca false en cada carga, así que esto
    // corre una vez por sesión de navegador. fetchAccessMatrix lo pone en true
    // pase lo que pase, o sea el efecto no se puede reentrar.
    if (user && !refreshed) {
      void fetchAccessMatrix();
    }
  }, [user, refreshed, fetchAccessMatrix]);

  return accessMatrix !== null;
}

/**
 * Primer destino de la lista que el usuario puede abrir de verdad, o null si
 * ninguno. Para elegir a dónde aterrizar sin mandarlo a una ruta que su rol
 * activo no puede abrir (rebotaría de vuelta).
 */
export function useFirstAllowed(
  candidates: ReadonlyArray<{ path: string; abilities: readonly string[] }>
): string | null {
  return useAuthStore(
    (s) =>
      candidates.find((candidate) =>
        candidate.abilities.some((ability) => evaluate(s.accessMatrix, s.currentRole, ability))
      )?.path ?? null
  );
}

/**
 * Versión imperativa (fuera de render: redirects, handlers, lógica suelta).
 * No es reactiva — dentro de componentes usar `useCan`/`useCanAny`.
 */
export function can(ability: string): boolean {
  const { accessMatrix, currentRole } = useAuthStore.getState();
  return evaluate(accessMatrix, currentRole, ability);
}

export function canAny(abilities: string[]): boolean {
  return abilities.some((ability) => can(ability));
}
