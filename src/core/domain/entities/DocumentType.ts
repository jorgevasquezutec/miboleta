// Domain Entity - DocumentType
export interface DocumentType {
    id: number;
    name: string;
    displayName: string;
    description: string | null;
    requiresSignature: boolean;
    isActive: boolean;
    sortOrder: number;
}

// Name to display name mapping for quick reference
export const documentTypeNames: Record<string, string> = {
    boleta_remuneraciones: 'Boleta de Remuneraciones',
    liquidacion_cts: 'Hoja Liquidación de CTS',
    liquidacion_utilidades: 'Hoja Liquidación de Participación de Utilidades',
    certificado_quinta: 'Certificado de Rentas de Quinta Categoría',
    contrato_trabajo: 'Contrato de Trabajo',
    legajo_personal: 'Legajo de Personal',
};
