import { describe, it, expect } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { extractNestedErrors, useFormErrors } from '../useFormErrors';
import { ApiError } from '@/infrastructure/http/apiClient';

describe('extractNestedErrors', () => {
    const selectedTenantIds = ['10', '20'];
    const indexToKey = (i: number) => selectedTenantIds[i];

    it('reagrupa tenants_config.0.hire_date bajo el id de la empresa que resuelve indexToKey(0)', () => {
        const fieldErrors = {
            document_text: 'Este número de documento ya está registrado',
            'tenants_config.0.hire_date': 'La fecha de inicio laboral no puede ser futura',
            'tenants_config.1.role_ids': 'Debe seleccionar al menos un rol',
        };

        const { nested, consumed } = extractNestedErrors(fieldErrors, 'tenants_config', indexToKey);

        expect(nested).toEqual({
            '10': { hire_date: 'La fecha de inicio laboral no puede ser futura' },
            '20': { role_ids: 'Debe seleccionar al menos un rol' },
        });
        expect(consumed.sort()).toEqual(
            ['tenants_config.0.hire_date', 'tenants_config.1.role_ids'].sort(),
        );
    });

    it('no consume un índice cuya fila ya no existe en indexToKey', () => {
        const fieldErrors = {
            'tenants_config.5.hire_date': 'La fecha de inicio laboral no puede ser futura',
        };

        const { nested, consumed } = extractNestedErrors(fieldErrors, 'tenants_config', indexToKey);

        expect(nested).toEqual({});
        expect(consumed).toEqual([]);
    });

    it('ignora campos que no matchean el prefijo', () => {
        const fieldErrors = { email: 'El correo ya está en uso' };

        const { nested, consumed } = extractNestedErrors(fieldErrors, 'tenants_config', indexToKey);

        expect(nested).toEqual({});
        expect(consumed).toEqual([]);
    });
});

describe('useFormErrors', () => {
    function makeValidationError(fieldErrors: Record<string, string>): ApiError {
        const messages = Object.values(fieldErrors);
        return new ApiError(messages[0] ?? 'Error de validación', 'validation', messages, fieldErrors, 422);
    }

    it('applyApiError sin knownFields: todo lo no anidado va a errors (conocido por defecto)', () => {
        const { result } = renderHook(() => useFormErrors());

        act(() => {
            result.current.applyApiError(
                makeValidationError({
                    document_text: 'Este número de documento ya está registrado',
                    email: 'El correo ya está en uso',
                }),
            );
        });

        expect(result.current.errors).toEqual({
            document_text: 'Este número de documento ya está registrado',
            email: 'El correo ya está en uso',
        });
        expect(result.current.formErrors).toEqual([]);
        expect(result.current.nestedErrors).toEqual({});
    });

    it('applyApiError con knownFields: lo que no está en la lista va a formErrors, nada se pierde', () => {
        const { result } = renderHook(() => useFormErrors({ knownFields: ['document_text'] }));

        act(() => {
            result.current.applyApiError(
                makeValidationError({
                    document_text: 'Este número de documento ya está registrado',
                    'some.unmapped.field': 'Un error sin control visible',
                }),
            );
        });

        expect(result.current.errors).toEqual({
            document_text: 'Este número de documento ya está registrado',
        });
        expect(result.current.formErrors).toEqual(['Un error sin control visible']);
    });

    it('applyApiError con nested: reparte en errors, nestedErrors y formErrors sin perder ningún mensaje', () => {
        const selectedTenantIds = ['10', '20'];
        const { result } = renderHook(() =>
            useFormErrors({ knownFields: ['document_text', 'email'] }),
        );

        const fieldErrors = {
            document_text: 'Este número de documento ya está registrado',
            'tenants_config.0.hire_date': 'La fecha de inicio laboral no puede ser futura',
            'some.unmapped.field': 'Un error sin control visible',
        };
        const totalMessages = Object.keys(fieldErrors).length;

        act(() => {
            result.current.applyApiError(makeValidationError(fieldErrors), {
                prefix: 'tenants_config',
                indexToKey: i => selectedTenantIds[i],
            });
        });

        expect(result.current.errors).toEqual({
            document_text: 'Este número de documento ya está registrado',
        });
        expect(result.current.nestedErrors).toEqual({
            '10': { hire_date: 'La fecha de inicio laboral no puede ser futura' },
        });
        expect(result.current.formErrors).toEqual(['Un error sin control visible']);

        const nestedMessageCount = Object.values(result.current.nestedErrors).reduce(
            (sum, fields) => sum + Object.keys(fields).length,
            0,
        );
        const distributedCount =
            Object.keys(result.current.errors).length + nestedMessageCount + result.current.formErrors.length;
        expect(distributedCount).toBe(totalMessages);
    });

    it('applyApiError devuelve el ApiError normalizado', () => {
        const { result } = renderHook(() => useFormErrors());
        const source = makeValidationError({ email: 'El correo ya está en uso' });

        let returned: ApiError | undefined;
        act(() => {
            returned = result.current.applyApiError(source);
        });

        expect(returned).toBe(source);
    });

    it('setFieldError / clearError / clearAll manejan el estado manual', () => {
        const { result } = renderHook(() => useFormErrors());

        act(() => {
            result.current.setFieldError('name', 'Requerido');
        });
        expect(result.current.errors).toEqual({ name: 'Requerido' });
        expect(result.current.hasErrors).toBe(true);

        act(() => {
            result.current.clearError('name');
        });
        expect(result.current.errors).toEqual({});
        expect(result.current.hasErrors).toBe(false);

        act(() => {
            result.current.setErrors({ email: 'Inválido' });
        });
        expect(result.current.errors).toEqual({ email: 'Inválido' });

        act(() => {
            result.current.clearAll();
        });
        expect(result.current.errors).toEqual({});
        expect(result.current.formErrors).toEqual([]);
        expect(result.current.nestedErrors).toEqual({});
    });
});
