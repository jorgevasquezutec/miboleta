import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DocumentCard } from '../DocumentCard';

describe('DocumentCard', () => {
  it('signed se rotula "Firmado por trabajador"', () => {
    render(<DocumentCard id="1" title="Boleta" category="payslip" status="signed" date="01/10/2026" />);
    expect(screen.getByText('Firmado por trabajador')).toBeInTheDocument();
  });

  it('muestra la insignia digital independiente del estado', () => {
    render(
      <DocumentCard
        id="1" title="Boleta" category="payslip" status="pending" date="01/10/2026"
        digitalSignature={{ method: 'pades_pyhanko' } as never} digitalSignatureStatus="signed"
        onSign={() => {}}
      />
    );
    expect(screen.getByText('Firma digital')).toBeInTheDocument();
    // El botón Firmar sigue con status pending aunque exista firma digital
    expect(screen.getByRole('button', { name: /Firmar/ })).toBeInTheDocument();
  });

  it('sin firma digital no hay insignia', () => {
    render(<DocumentCard id="1" title="Boleta" category="payslip" status="pending" date="x" />);
    expect(screen.queryByTestId('digital-signature-badge')).toBeNull();
  });
});
