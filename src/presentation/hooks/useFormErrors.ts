import { useCallback, useState } from 'react';
import { ApiError, toApiError } from '@/infrastructure/http/apiClient';

/** Escapa un string para usarlo literal dentro de una RegExp. */
function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export interface ExtractedNestedErrors {
    /** clave resuelta por `indexToKey` (p.ej. tenantId) -> campo del backend -> mensaje. */
    nested: Record<string, Record<string, string>>;
    /** claves originales de `fieldErrors` (p.ej. `tenants_config.0.hire_date`) ya repartidas en `nested`. */
    consumed: string[];
}

/**
 * Reagrupa los errores anidados con forma `${prefix}.${i}.${campo}` (p.ej.
 * `tenants_config.0.hire_date`) bajo la clave de fila que corresponde,
 * resuelta con `indexToKey` — construido desde el MISMO array que arma el
 * payload (ver UserFormPage: `i => selectedTenantIds[i]`), para que el error
 * se pinte junto a la empresa correcta.
 *
 * Si `indexToKey(i)` no resuelve (fila ya no existe en el array), ese campo
 * NO se consume: queda en `fieldErrors` para que el llamador lo trate como
 * desconocido en vez de perderlo o pintarlo en la fila equivocada.
 */
export function extractNestedErrors(
    fieldErrors: Record<string, string>,
    prefix: string,
    indexToKey: (index: number) => string | undefined,
): ExtractedNestedErrors {
    const nested: Record<string, Record<string, string>> = {};
    const consumed: string[] = [];
    const pattern = new RegExp(`^${escapeRegExp(prefix)}\\.(\\d+)\\.(.+)$`);

    for (const [field, message] of Object.entries(fieldErrors)) {
        const match = field.match(pattern);
        if (!match) continue;

        const index = Number(match[1]);
        const nestedField = match[2];
        const key = indexToKey(index);
        if (key === undefined) continue;

        nested[key] = { ...nested[key], [nestedField]: message };
        consumed.push(field);
    }

    return { nested, consumed };
}

export interface UseFormErrorsOptions {
    /**
     * Campos que el formulario sabe pintar junto a un control. Si se omite,
     * TODOS los campos restantes (tras extraer los anidados) se consideran
     * conocidos. Si se pasa, lo que no está en la lista va a `formErrors`.
     */
    knownFields?: string[];
}

export interface UseFormErrorsResult {
    /** campo -> mensaje, para pintar junto al input correspondiente. */
    errors: Record<string, string>;
    /** Mensajes sin control asignado: se pintan en `FormErrorSummary`. */
    formErrors: string[];
    /** clave de fila (p.ej. tenantId) -> campo del backend -> mensaje. */
    nestedErrors: Record<string, Record<string, string>>;
    hasErrors: boolean;
    setFieldError(field: string, message: string): void;
    setErrors(errors: Record<string, string>): void;
    clearError(field: string): void;
    clearAll(): void;
    /**
     * Normaliza `error` con `toApiError` y reparte sus errores por campo en
     * `errors`/`nestedErrors`/`formErrors` — ningún mensaje de campo se
     * queda fuera de esas tres salidas. Si se pasa `nested`, primero corre
     * `extractNestedErrors` con ese prefijo/resolver; lo no consumido se
     * reparte entre conocido (`errors`) y desconocido (`formErrors`) según
     * `options.knownFields`. Devuelve el `ApiError` normalizado.
     */
    applyApiError(
        error: unknown,
        nested?: { prefix: string; indexToKey: (index: number) => string | undefined },
    ): ApiError;
}

export function useFormErrors(options?: UseFormErrorsOptions): UseFormErrorsResult {
    const [errors, setErrorsState] = useState<Record<string, string>>({});
    const [formErrors, setFormErrors] = useState<string[]>([]);
    const [nestedErrors, setNestedErrors] = useState<Record<string, Record<string, string>>>({});
    const knownFields = options?.knownFields;

    const setFieldError = useCallback((field: string, message: string) => {
        setErrorsState(prev => ({ ...prev, [field]: message }));
    }, []);

    const setErrors = useCallback((next: Record<string, string>) => {
        setErrorsState(next);
    }, []);

    const clearError = useCallback((field: string) => {
        setErrorsState(prev => {
            if (!(field in prev)) return prev;
            const rest = { ...prev };
            delete rest[field];
            return rest;
        });
    }, []);

    const clearAll = useCallback(() => {
        setErrorsState({});
        setFormErrors([]);
        setNestedErrors({});
    }, []);

    const applyApiError = useCallback(
        (
            error: unknown,
            nested?: { prefix: string; indexToKey: (index: number) => string | undefined },
        ): ApiError => {
            const apiError = toApiError(error);
            let remaining = apiError.fieldErrors;

            let nestedMap: Record<string, Record<string, string>> = {};
            if (nested) {
                const extracted = extractNestedErrors(remaining, nested.prefix, nested.indexToKey);
                nestedMap = extracted.nested;
                if (extracted.consumed.length > 0) {
                    const consumedSet = new Set(extracted.consumed);
                    remaining = Object.fromEntries(
                        Object.entries(remaining).filter(([field]) => !consumedSet.has(field)),
                    );
                }
            }

            const known: Record<string, string> = {};
            const unknown: string[] = [];

            for (const [field, message] of Object.entries(remaining)) {
                const isKnown = !knownFields || knownFields.includes(field);
                if (isKnown) {
                    known[field] = message;
                } else {
                    unknown.push(message);
                }
            }

            setErrorsState(known);
            setNestedErrors(nestedMap);
            setFormErrors(unknown);

            return apiError;
        },
        [knownFields],
    );

    const hasErrors =
        Object.keys(errors).length > 0 || formErrors.length > 0 || Object.keys(nestedErrors).length > 0;

    return {
        errors,
        formErrors,
        nestedErrors,
        hasErrors,
        setFieldError,
        setErrors,
        clearError,
        clearAll,
        applyApiError,
    };
}
