import { describe, it, expect, vi } from 'vitest';
import { render, renderHook, act, screen } from '@testing-library/react';
import { useFormErrors } from '@/presentation/hooks/useFormErrors';
import { TenantAssociation } from '@/core/domain/entities/User';
import { TenantAssignmentCard, EMPTY_TENANT_EXTRA } from '../TenantAssignmentCard';

/**
 * TenantMultiSelector (hijo de TenantAssignmentCard) dispara una búsqueda
 * real al montar, vía useTenantSearch -> tenantsStore.searchTenants (axios),
 * incluso sin abrir el popover (ver useTenantSearch.ts:41-43). Se stubea
 * para que el test sea determinista y no dependa de red; no es lo que este
 * test cubre.
 */
vi.mock('@/presentation/hooks/useTenantSearch', () => ({
    useTenantSearch: () => ({
        searchQuery: '',
        results: [],
        isSearching: false,
        hasMore: false,
        pagination: null,
        setSearchQuery: vi.fn(),
        loadMore: vi.fn(),
        clear: vi.fn(),
    }),
}));

/**
 * Error "de axios" mínimo: basta con `isAxiosError: true` para que
 * `axios.isAxiosError` lo reconozca (mismo helper que apiClient.test.ts).
 */
function makeAxiosError(options: { status: number; data: unknown }) {
    return {
        isAxiosError: true,
        message: `Request failed with status code ${options.status}`,
        response: { status: options.status, data: options.data },
    };
}

/**
 * Payload EXACTO del incidente reportado (ver
 * openspec/changes/centralizar-manejo-de-errores-api/design.md #Context):
 * guardar un usuario con documento duplicado y fecha de inicio laboral
 * futura devuelve un 422 con dos errores — uno plano (document_text) y uno
 * anidado por empresa (tenants_config.0.hire_date, índice 0 = la única
 * empresa seleccionada, id '5').
 */
const incidentPayload = {
    message: 'Este número de documento ya está registrado (and 1 more error)',
    errors: {
        document_text: ['Este número de documento ya está registrado'],
        'tenants_config.0.hire_date': ['La fecha de inicio laboral no puede ser futura'],
    },
};

describe('caso del incidente: documento duplicado + fecha de inicio laboral futura', () => {
    it('applyApiError reparte el payload: document_text a errors, hire_date a nestedErrors bajo la empresa 5', () => {
        // indexToKey: i => ['5'][i] refleja UserFormPage.handleSubmit, donde
        // selectedTenantIds = ['5'] es el array que arma tenants_config.
        const { result } = renderHook(() => useFormErrors());

        act(() => {
            result.current.applyApiError(
                makeAxiosError({ status: 422, data: incidentPayload }),
                { prefix: 'tenants_config', indexToKey: i => ['5'][i] },
            );
        });

        expect(result.current.errors.document_text).toBe(
            'Este número de documento ya está registrado',
        );
        expect(result.current.nestedErrors['5']?.hire_date).toBe(
            'La fecha de inicio laboral no puede ser futura',
        );
    });

    /**
     * Montar UserFormPage completa para este caso es inviable: depende de
     * useNavigate/useParams (react-router), useAuthStore, useCan,
     * userRepository.findById/getUsers y roleRepository.getAll — todo
     * mockeable, pero sin aportar nada a lo que este test verifica (que el
     * mensaje de fecha llega al control correcto). En su lugar se monta
     * TenantAssignmentCard con el `nestedErrors` REAL que produjo el hook de
     * arriba (no un literal a mano), que es exactamente lo que
     * UserFormPage le pasa como `fieldErrorsByTenant`: así el test cubre el
     * pipeline completo, de applyApiError al DOM.
     */
    it('TenantAssignmentCard pinta el error de hire_date bajo el input de fecha de la empresa correcta', () => {
        const { result } = renderHook(() => useFormErrors());
        act(() => {
            result.current.applyApiError(
                makeAxiosError({ status: 422, data: incidentPayload }),
                { prefix: 'tenants_config', indexToKey: i => ['5'][i] },
            );
        });

        const tenant: TenantAssociation = {
            id: '5',
            name: 'ACME SAC',
            ruc: '20123456789',
            is_primary: true,
        };

        render(
            <TenantAssignmentCard
                selectedTenantIds={['5']}
                primaryTenantId="5"
                selectedTenants={[tenant]}
                supervisorsByTenant={{}}
                availableRoles={[]}
                extrasByTenant={{ '5': EMPTY_TENANT_EXTRA }}
                fieldErrorsByTenant={result.current.nestedErrors}
                onTenantSelectionChange={vi.fn()}
                onTenantsChange={vi.fn()}
                onPrimaryChange={vi.fn()}
                onSupervisorChange={vi.fn()}
                onExtraChange={vi.fn()}
            />,
        );

        expect(
            screen.getByText('La fecha de inicio laboral no puede ser futura'),
        ).toBeInTheDocument();
    });
});
