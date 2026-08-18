import { describe, it, expect, vi, beforeEach } from 'vitest';

// vi.mock is hoisted, so the factory can't reference outer variables directly
// (see ReportsRepository.test.ts): vi.hoisted() creates the mock fn before
// the hoisting happens.
const { toastError } = vi.hoisted(() => ({ toastError: vi.fn() }));
vi.mock('sonner', () => ({
    toast: { error: toastError },
}));

import { showApiError } from '../showApiError';
import { ApiError } from '@/infrastructure/http/apiClient';

describe('showApiError', () => {
    beforeEach(() => {
        toastError.mockClear();
    });

    it('con un solo mensaje: toast simple, sin título', () => {
        const error = new ApiError('El correo ya está en uso', 'validation', ['El correo ya está en uso'], {}, 422);

        const result = showApiError(error);

        expect(toastError).toHaveBeenCalledWith('El correo ya está en uso');
        expect(result).toBe(error);
    });

    it('con varios mensajes: título + description listando todos, hasta 5', () => {
        const messages = ['msg1', 'msg2', 'msg3'];
        const error = new ApiError('msg1', 'validation', messages, {}, 422);

        showApiError(error, 'Revisa estos errores');

        expect(toastError).toHaveBeenCalledWith(
            'Revisa estos errores',
            expect.objectContaining({ description: 'msg1\nmsg2\nmsg3' }),
        );
    });

    it('con más de 5 mensajes: capa a 5 líneas y agrega "+N más"', () => {
        const messages = ['1', '2', '3', '4', '5', '6', '7'];
        const error = new ApiError('1', 'validation', messages, {}, 422);

        showApiError(error);

        expect(toastError).toHaveBeenCalledWith(
            expect.any(String),
            expect.objectContaining({ description: '1\n2\n3\n4\n5\n+2 más' }),
        );
    });

    it('normaliza errores crudos con toApiError antes de mostrarlos', () => {
        const rawAxiosError = {
            isAxiosError: true,
            message: 'Request failed',
            response: { status: 500, data: { message: 'Error interno' } },
        };

        const result = showApiError(rawAxiosError);

        expect(toastError).toHaveBeenCalledWith('Error interno');
        expect(result).toBeInstanceOf(ApiError);
        expect(result.code).toBe('server');
    });
});
