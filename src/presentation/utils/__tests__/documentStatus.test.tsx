import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { getDigitalSignatureBadge, getDocumentStatusLabel } from '../documentStatus';

const pades = { method: 'pades_pyhanko' } as never;

describe('getDigitalSignatureBadge', () => {
  it('signed → "Firma digital"', () => {
    render(<>{getDigitalSignatureBadge({ digitalSignature: pades, digitalSignatureStatus: 'signed' })}</>);
    expect(screen.getByText('Firma digital')).toBeInTheDocument();
  });

  it('pending → "Firmando…"', () => {
    render(<>{getDigitalSignatureBadge({ digitalSignature: pades, digitalSignatureStatus: 'pending' })}</>);
    expect(screen.getByText('Firmando…')).toBeInTheDocument();
  });

  it('failed → "Error firma"', () => {
    render(<>{getDigitalSignatureBadge({ digitalSignature: pades, digitalSignatureStatus: 'failed' })}</>);
    expect(screen.getByText('Error firma')).toBeInTheDocument();
  });

  it('sin firma ni estado → nada', () => {
    expect(getDigitalSignatureBadge({ digitalSignature: null, digitalSignatureStatus: null })).toBeNull();
  });

  it('con firma pero sin estado se trata como firmada', () => {
    render(<>{getDigitalSignatureBadge({ digitalSignature: pades, digitalSignatureStatus: null })}</>);
    expect(screen.getByText('Firma digital')).toBeInTheDocument();
  });
});

describe('etiqueta de signed', () => {
  it('es "Firmado por trabajador"', () => {
    expect(getDocumentStatusLabel('signed')).toBe('Firmado por trabajador');
  });
});
