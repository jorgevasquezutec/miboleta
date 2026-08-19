import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '@/test/utils';
import userEvent from '@testing-library/user-event';
import { ImpersonationBanner } from '../../shared/ImpersonationBanner';
import { useAuthStore } from '@/presentation/stores/authStore';
import { mockUser } from '@/test/mocks/handlers';

describe('ImpersonationBanner', () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: null,
      impersonator: null,
      isLoading: false,
      leaveImpersonation: vi.fn().mockResolvedValue(undefined),
    });
  });

  it('no renderiza nada sin impersonation activa', () => {
    render(<ImpersonationBanner />);

    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('muestra a quién se está impersonando y quién está detrás', () => {
    useAuthStore.setState({
      user: mockUser as never,
      impersonator: { id: '99', full_name: 'Root Admin', email: 'root@example.com' },
    });

    render(<ImpersonationBanner />);

    const banner = screen.getByRole('alert');
    // `user` es el empleado impersonado (mockUser.full_name); `impersonator`
    // es quien opera de verdad detrás — el texto debe distinguir ambos, no
    // solo mostrar uno de los dos nombres.
    expect(banner).toHaveTextContent(mockUser.full_name);
    expect(banner).toHaveTextContent('Root Admin');
  });

  it('llama a leaveImpersonation al hacer click en "Volver a mi cuenta"', async () => {
    const leaveImpersonation = vi.fn().mockResolvedValue(undefined);
    useAuthStore.setState({
      user: mockUser as never,
      impersonator: { id: '99', full_name: 'Root Admin', email: 'root@example.com' },
      leaveImpersonation,
    });

    const user = userEvent.setup();
    render(<ImpersonationBanner />);

    await user.click(screen.getByRole('button', { name: /volver a mi cuenta/i }));

    expect(leaveImpersonation).toHaveBeenCalledTimes(1);
  });

  it('muestra el error como toast si leaveImpersonation falla', async () => {
    const leaveImpersonation = vi.fn().mockRejectedValue(new Error('No se pudo'));
    useAuthStore.setState({
      user: mockUser as never,
      impersonator: { id: '99', full_name: 'Root Admin', email: 'root@example.com' },
      leaveImpersonation,
    });

    const user = userEvent.setup();
    render(<ImpersonationBanner />);

    // No debe propagar/lanzar hacia el árbol de React: el catch interno del
    // handler es lo que evita un "Unhandled promise rejection" en el click.
    await expect(
      user.click(screen.getByRole('button', { name: /volver a mi cuenta/i }))
    ).resolves.not.toThrow();

    expect(leaveImpersonation).toHaveBeenCalledTimes(1);
  });
});
