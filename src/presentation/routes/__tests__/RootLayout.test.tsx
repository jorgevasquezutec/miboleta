import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { RootLayout } from '../RootLayout';
import { useAuthStore } from '@/presentation/stores/authStore';
import { mockUser } from '@/test/mocks/handlers';

// NotificationBell (montado dentro de Navbar) intenta abrir una conexión
// realtime (Echo/Pusher) al montar, fuera del alcance de MSW: sin este mock,
// cada test de este archivo deja un "Unhandled Rejection" (undici) que no
// tiene nada que ver con lo que se está probando aquí (el bootstrap de
// sesión y el banner de impersonation).
vi.mock('@/presentation/components/notifications/NotificationBell', () => ({
  NotificationBell: () => null,
}));

/**
 * Ver CONTRATO-IMPERSONATION: enterImpersonation/leaveImpersonation hacen una
 * recarga dura y borran 'auth-storage' ANTES de recargar (evita rehidratar la
 * identidad vieja). Eso deja a RootLayout con `user === null` en el primer
 * render tras la recarga, aunque la sesión SÍ sea válida (las cookies
 * HttpOnly sobrevivieron). RootLayout es quien debe intentar /me antes de
 * rendirse y mandar a login — estos tests cubren justo esa carrera.
 */
function renderRootLayout(initialPath = '/') {
  return render(
    <MemoryRouter initialEntries={[initialPath]}>
      <Routes>
        <Route path="/login" element={<div>LOGIN</div>} />
        <Route path="/" element={<RootLayout />}>
          <Route index element={<div>HOME</div>} />
        </Route>
      </Routes>
    </MemoryRouter>
  );
}

describe('RootLayout', () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: null,
      currentTenant: null,
      currentRole: null,
      accessMatrix: {},
      impersonator: null,
      isLoading: false,
      error: null,
    });
  });

  it('espera la respuesta de /me antes de redirigir a login (no rebota de inmediato)', async () => {
    let resolveMe: () => void;
    const me = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          resolveMe = resolve;
        })
    );
    useAuthStore.setState({ me: me as never });

    renderRootLayout('/');

    // Mientras /me está en vuelo: ni el layout ni el login todavía.
    expect(screen.queryByText('LOGIN')).not.toBeInTheDocument();
    expect(screen.queryByText('HOME')).not.toBeInTheDocument();
    expect(me).toHaveBeenCalledTimes(1);

    // /me confirma que no hay sesión válida (user sigue null): recién ahora
    // se redirige a login.
    resolveMe!();
    await waitFor(() => expect(screen.getByText('LOGIN')).toBeInTheDocument());
  });

  it('si /me repuebla el usuario, renderiza el layout en vez de redirigir', async () => {
    const me = vi.fn(async () => {
      useAuthStore.setState({ user: mockUser as never });
    });
    useAuthStore.setState({ me: me as never });

    renderRootLayout('/');

    await waitFor(() => expect(screen.getByText('HOME')).toBeInTheDocument());
    expect(screen.queryByText('LOGIN')).not.toBeInTheDocument();
  });

  it('con sesión ya en caché, no bloquea el render mientras /me revalida en segundo plano', () => {
    useAuthStore.setState({ user: mockUser as never, me: vi.fn(() => new Promise(() => {})) as never });

    renderRootLayout('/');

    // No hay loader ni espera: la copia cacheada ya es suficiente para pintar.
    expect(screen.getByText('HOME')).toBeInTheDocument();
  });

  it('monta el ImpersonationBanner cuando la sesión está impersonada', async () => {
    useAuthStore.setState({
      user: mockUser as never,
      impersonator: { id: '99', full_name: 'Root Admin', email: 'root@example.com' },
      me: vi.fn().mockResolvedValue(undefined) as never,
    });

    renderRootLayout('/');

    expect(screen.getByRole('alert')).toHaveTextContent('conectado como Root Admin');
  });

  it('no muestra el banner en una sesión normal (sin impersonator)', () => {
    useAuthStore.setState({
      user: mockUser as never,
      impersonator: null,
      me: vi.fn().mockResolvedValue(undefined) as never,
    });

    renderRootLayout('/');

    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});
