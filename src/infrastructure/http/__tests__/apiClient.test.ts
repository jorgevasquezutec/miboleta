import { describe, it, expect } from 'vitest';
import { getErrorMessage, toApiError, ApiError } from '../apiClient';

/**
 * Construye un error "de axios" mínimo: basta con `isAxiosError: true` para
 * que `axios.isAxiosError` lo reconozca (ver axios/lib/helpers/isAxiosError.js).
 */
function makeAxiosError(options: {
    status: number;
    data: unknown;
    message?: string;
}) {
    return {
        isAxiosError: true,
        message: options.message ?? `Request failed with status code ${options.status}`,
        response: { status: options.status, data: options.data },
    };
}

function makeNetworkError(message = 'Network Error') {
    return { isAxiosError: true, message, request: {} };
}

describe('toApiError', () => {
    it('en el payload real del incidente: message sin el sufijo de Laravel, messages con los dos, fieldErrors con las dos claves', () => {
        const raw = makeAxiosError({
            status: 422,
            data: {
                message: 'Este número de documento ya está registrado (and 1 more error)',
                errors: {
                    document_text: ['Este número de documento ya está registrado'],
                    'tenants_config.0.hire_date': ['La fecha de inicio laboral no puede ser futura'],
                },
            },
        });

        const apiError = toApiError(raw);

        expect(apiError).toBeInstanceOf(ApiError);
        expect(apiError.message).not.toContain('(and 1 more error)');
        expect(apiError.message).toBe('Este número de documento ya está registrado');
        expect(apiError.messages).toEqual([
            'Este número de documento ya está registrado',
            'La fecha de inicio laboral no puede ser futura',
        ]);
        expect(apiError.messages.length).toBe(2);
        expect(apiError.status).toBe(422);
        expect(apiError.code).toBe('validation');
        expect(apiError.fieldErrors).toEqual({
            document_text: 'Este número de documento ya está registrado',
            'tenants_config.0.hire_date': 'La fecha de inicio laboral no puede ser futura',
        });
    });

    it('es idempotente: aplicarlo a un ApiError ya normalizado devuelve la misma instancia', () => {
        const raw = makeAxiosError({
            status: 422,
            data: {
                message: 'Este número de documento ya está registrado (and 1 more error)',
                errors: {
                    document_text: ['Este número de documento ya está registrado'],
                    'tenants_config.0.hire_date': ['La fecha de inicio laboral no puede ser futura'],
                },
            },
        });

        const normalized = toApiError(raw);
        expect(toApiError(normalized)).toBe(normalized);
    });

    it('403 → code "forbidden"', () => {
        const apiError = toApiError(
            makeAxiosError({ status: 403, data: { message: 'No tienes permiso para esto' } }),
        );
        expect(apiError.status).toBe(403);
        expect(apiError.code).toBe('forbidden');
        expect(apiError.message).toBe('No tienes permiso para esto');
        expect(apiError.messages).toEqual(['No tienes permiso para esto']);
    });

    it('404 → code "not_found"', () => {
        const apiError = toApiError(
            makeAxiosError({ status: 404, data: { message: 'Recurso no encontrado' } }),
        );
        expect(apiError.status).toBe(404);
        expect(apiError.code).toBe('not_found');
    });

    it('500 → code "server"', () => {
        const apiError = toApiError(
            makeAxiosError({ status: 500, data: { message: 'Error interno' } }),
        );
        expect(apiError.status).toBe(500);
        expect(apiError.code).toBe('server');
    });

    it('sin error.response (error de red) → status undefined, code "network"', () => {
        const apiError = toApiError(makeNetworkError('Network Error'));
        expect(apiError.status).toBeUndefined();
        expect(apiError.code).toBe('network');
        expect(apiError.message).toBe('Network Error');
        expect(apiError.messages).toEqual(['Network Error']);
        expect(apiError.fieldErrors).toEqual({});
    });

    it('status sin mapeo explícito → code "unknown"', () => {
        const apiError = toApiError(
            makeAxiosError({ status: 400, data: { message: 'Solicitud inválida' } }),
        );
        expect(apiError.code).toBe('unknown');
    });

    it('error no-axios (Error genérico) se normaliza igual', () => {
        const apiError = toApiError(new Error('algo explotó'));
        expect(apiError).toBeInstanceOf(ApiError);
        expect(apiError.code).toBe('unknown');
        expect(apiError.status).toBeUndefined();
        expect(apiError.messages).toEqual(['algo explotó']);
    });
});

describe('getErrorMessage', () => {
    it('en 422 con errors, ignora el "(and N more errors)" de data.message', () => {
        const raw = makeAxiosError({
            status: 422,
            data: {
                message: 'Este número de documento ya está registrado (and 1 more error)',
                errors: {
                    document_text: ['Este número de documento ya está registrado'],
                    'tenants_config.0.hire_date': ['La fecha de inicio laboral no puede ser futura'],
                },
            },
        });

        expect(getErrorMessage(raw)).toBe('Este número de documento ya está registrado');
    });

    it('fuera de 422, sigue la prioridad data.error > data.message', () => {
        const raw = makeAxiosError({
            status: 400,
            data: { error: 'mensaje de error específico', message: 'mensaje genérico' },
        });

        expect(getErrorMessage(raw)).toBe('mensaje de error específico');
    });
});
