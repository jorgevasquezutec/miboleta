import { describe, it, expect } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { VerifySignatureModal } from '../VerifySignatureModal';

const base = { verifiable: true, intact: true, valid: true, trusted: true, coversWholeFile: true, signerSubject: 'CN=X', signingTime: null, tsaApplied: false, tsaTime: null };

function renderModal(includesConformity: boolean | null | undefined) {
  render(
    <VerifySignatureModal
      isOpen onClose={() => {}} isLoading={false} error={null}
      result={{ ...base, includesConformity }}
    />
  );
}

function conformityRow() {
  return within(screen.getByText('Incluye conformidad del trabajador').closest('div')!.parentElement!);
}

describe('VerifySignatureModal', () => {
  it('muestra Sí cuando incluye la conformidad', () => {
    renderModal(true);
    expect(conformityRow().getByText('Sí')).toBeInTheDocument();
    expect(conformityRow().queryByText('No')).not.toBeInTheDocument();
  });

  it('muestra No cuando no la incluye', () => {
    renderModal(false);
    expect(conformityRow().getByText('No')).toBeInTheDocument();
    expect(conformityRow().queryByText('Sí')).not.toBeInTheDocument();
  });

  it('muestra No disponible con null o undefined', () => {
    renderModal(null);
    expect(conformityRow().getByText('No disponible')).toBeInTheDocument();
  });

  it('undefined también es No disponible', () => {
    renderModal(undefined);
    expect(conformityRow().getByText('No disponible')).toBeInTheDocument();
  });
});
