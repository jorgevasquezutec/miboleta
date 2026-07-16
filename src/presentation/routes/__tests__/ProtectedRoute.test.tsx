import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { ProtectedRoute } from '../ProtectedRoute';
import { useAuthStore } from '@/presentation/stores/authStore';

/**
 * REGRESIÓN: "Maximum update depth exceeded".
 *
 * ProtectedRoute gatea por ability contra authStore.accessMatrix. Cuando el mapa
 * no está (sesión restaurada de localStorage, que antes no lo persistía), toda
 * ability es false -> la ruta denegaba -> redirigía a "/" -> "/" reenviaba a la
 * ruta -> denegaba otra vez... hasta agotar la pila de React.
 *
 * La regla que lo evita: con la matriz DESCONOCIDA hay que esperar, no denegar.
 */

const MATRIX = {
  'dashboard.org_metrics': ['admin', 'admin_tenant'],
  'vacations.approve_reject_team': ['admin', 'admin_tenant', 'aprobador'],
};

// "/" es terminal a propósito (en la app real es RootRedirect). Así el test
// puede afirmar si ProtectedRoute denegó —aterriza en REBOTE— sin montar un
// ping-pong infinito dentro del propio test.
function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/" element={<div>REBOTE</div>} />
        <Route
          path="/admin"
          element={
            <ProtectedRoute requires="dashboard.org_metrics">
              <div>PANEL</div>
            </ProtectedRoute>
          }
        />
        <Route path="/login" element={<div>LOGIN</div>} />
      </Routes>
    </MemoryRouter>
  );
}

describe('ProtectedRoute', () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: { id: '1', name: 'Ana', role: 'admin' } as never,
      currentRole: 'admin',
      currentTenant: null,
      accessMatrix: null,
      accessMatrixRefreshed: false,
    });
    // El fetch de recuperación no debe disparar red en el test.
    useAuthStore.setState({ fetchAccessMatrix: vi.fn().mockResolvedValue(undefined) });
  });

  it('espera en vez de denegar mientras la matriz es desconocida', () => {
    renderAt('/admin');

    // La clave: NO rebota. Antes denegaba (matriz null => toda ability false) y
    // caía a "/", que en la app reenvía de vuelta -> bucle infinito.
    expect(screen.queryByText('REBOTE')).not.toBeInTheDocument();
    expect(screen.queryByText('PANEL')).not.toBeInTheDocument();
  });

  it('intenta recuperar la matriz si falta y hay sesión', () => {
    const fetchAccessMatrix = vi.fn().mockResolvedValue(undefined);
    useAuthStore.setState({ fetchAccessMatrix });

    renderAt('/admin');

    expect(fetchAccessMatrix).toHaveBeenCalled();
  });

  it('refresca la matriz aunque ya haya una en caché (podría estar vieja)', () => {
    const fetchAccessMatrix = vi.fn().mockResolvedValue(undefined);
    useAuthStore.setState({
      accessMatrix: MATRIX, // hay copia persistida...
      accessMatrixRefreshed: false, // ...pero todavía no se refrescó en esta carga
      fetchAccessMatrix,
    });

    renderAt('/admin');

    // Antes el refetch era `if (!accessMatrix)`, así que una copia
    // vieja-pero-presente se quedaba congelada: al agregar una ability nueva a
    // config/access_matrix.php, quien tenía sesión abierta no la tenía en su
    // mapa y el menú se la escondía hasta desloguearse.
    expect(fetchAccessMatrix).toHaveBeenCalled();
  });

  it('no vuelve a pedir la matriz si ya se refrescó en esta carga', () => {
    const fetchAccessMatrix = vi.fn().mockResolvedValue(undefined);
    useAuthStore.setState({
      accessMatrix: MATRIX,
      accessMatrixRefreshed: true,
      fetchAccessMatrix,
    });

    renderAt('/admin');

    expect(fetchAccessMatrix).not.toHaveBeenCalled();
  });

  it('deja pasar cuando el rol activo tiene la ability', () => {
    useAuthStore.setState({ accessMatrix: MATRIX, currentRole: 'admin' });

    renderAt('/admin');

    expect(screen.getByText('PANEL')).toBeInTheDocument();
  });

  it('deniega cuando el rol activo no tiene la ability', () => {
    // aprobador no está en dashboard.org_metrics: la ruta lo manda a "/".
    useAuthStore.setState({ accessMatrix: MATRIX, currentRole: 'aprobador' });

    renderAt('/admin');

    expect(screen.getByText('REBOTE')).toBeInTheDocument();
    expect(screen.queryByText('PANEL')).not.toBeInTheDocument();
  });

  it('manda al login sin sesión, aunque la matriz falte', () => {
    useAuthStore.setState({ user: null, currentRole: null, accessMatrix: null });

    renderAt('/admin');

    expect(screen.getByText('LOGIN')).toBeInTheDocument();
  });
});
