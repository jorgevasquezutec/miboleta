import { useState, useEffect, useCallback } from 'react';
import { useTenantSearch } from '@/presentation/hooks/useTenantSearch';
import { Tenant } from '@/core/domain/entities/Tenant';
import { TenantAssociation } from '@/core/domain/entities/User';
import { Button } from '@/presentation/components/ui/button';
import { Badge } from '@/presentation/components/ui/badge';
import { Checkbox } from '@/presentation/components/ui/checkbox';
import { ScrollArea } from '@/presentation/components/ui/scroll-area';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/presentation/components/ui/popover';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/presentation/components/ui/command';
import { Building2, Star, Loader2, ChevronDown, X } from 'lucide-react';

// Tipo que acepta tanto Tenant completo como TenantAssociation
type TenantInfo = Tenant | TenantAssociation | { id: string; name: string; ruc: string };

export interface TenantMultiSelectorProps {
  /** IDs de tenants seleccionados */
  selectedTenantIds: string[];
  /** Callback cuando cambia la selección */
  onSelectionChange: (tenantIds: string[]) => void;
  /** ID del tenant primario */
  primaryTenantId: string | null;
  /** Callback cuando cambia el tenant primario */
  onPrimaryChange: (tenantId: string | null) => void;
  /** Tenants ya seleccionados (para mostrar info completa) - acepta Tenant o TenantAssociation */
  selectedTenants?: TenantInfo[];
  /** Número mínimo de selecciones requeridas */
  minSelections?: number;
  /** Número máximo de selecciones permitidas */
  maxSelections?: number;
  /** Texto del placeholder */
  placeholder?: string;
  /** Mostrar error */
  error?: string;
  /** Deshabilitado */
  disabled?: boolean;
  /** Callback con info detallada de tenants seleccionados */
  onTenantsChange?: (tenants: TenantInfo[]) => void;
}

/**
 * Selector multi-tenant con búsqueda server-side y paginación
 * Permite seleccionar múltiples organizaciones y marcar una como primaria
 *
 * @example
 * ```tsx
 * <TenantMultiSelector
 *   selectedTenantIds={selectedIds}
 *   onSelectionChange={setSelectedIds}
 *   primaryTenantId={primaryId}
 *   onPrimaryChange={setPrimaryId}
 *   minSelections={1}
 * />
 * ```
 */
export function TenantMultiSelector({
  selectedTenantIds,
  onSelectionChange,
  primaryTenantId,
  onPrimaryChange,
  selectedTenants = [],
  minSelections = 0,
  maxSelections,
  placeholder = 'Buscar organizaciones...',
  error,
  disabled = false,
  onTenantsChange,
}: TenantMultiSelectorProps) {
  const [open, setOpen] = useState(false);

  // Inicializar cache con selectedTenants (normalizar IDs a string)
  const [tenantsCache, setTenantsCache] = useState<Map<string, TenantInfo>>(() => {
    const initialCache = new Map<string, TenantInfo>();
    selectedTenants.forEach(tenant => {
      initialCache.set(String(tenant.id), tenant);
    });
    return initialCache;
  });

  const { searchQuery, setSearchQuery, results, isSearching, hasMore, loadMore } =
    useTenantSearch();

  // Actualizar cache cuando cambian selectedTenants o results (normalizar IDs a string)
  useEffect(() => {
    setTenantsCache(prev => {
      let hasChanges = false;

      // Check if we need to add selectedTenants
      selectedTenants.forEach(tenant => {
        if (!prev.has(String(tenant.id))) {
          hasChanges = true;
        }
      });

      // Check if we need to add results
      results.forEach(tenant => {
        if (!prev.has(String(tenant.id))) {
          hasChanges = true;
        }
      });

      if (!hasChanges) {
        return prev;
      }

      const newCache = new Map(prev);

      // Agregar selectedTenants al cache
      selectedTenants.forEach(tenant => {
        newCache.set(String(tenant.id), tenant);
      });

      // Agregar results al cache
      results.forEach(tenant => {
        newCache.set(String(tenant.id), tenant);
      });

      return newCache;
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(selectedTenants), results]);

  // Notificar al padre sobre los detalles de los tenants seleccionados
  useEffect(() => {
    if (onTenantsChange) {
      const selectedInfo = selectedTenantIds
        .map(id => tenantsCache.get(String(id)))
        .filter(Boolean) as TenantInfo[];
      onTenantsChange(selectedInfo);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedTenantIds, tenantsCache]);

  // Combinar tenants del cache y resultados de búsqueda
  const getDisplayTenants = () => {
    const displayMap = new Map<string, TenantInfo>();

    // 1. Agregar todos los tenants seleccionados (siempre visibles)
    selectedTenantIds.forEach(id => {
      const tenant = tenantsCache.get(String(id));
      if (tenant) {
        displayMap.set(String(id), tenant);
      }
    });

    // 2. Agregar resultados de búsqueda
    results.forEach(tenant => {
      displayMap.set(String(tenant.id), tenant);
    });

    return Array.from(displayMap.values());
  };

  const displayTenants = getDisplayTenants();

  const handleToggleTenant = (tenantId: string, _tenant: TenantInfo) => {
    const isSelected = selectedTenantIds.includes(tenantId);

    if (isSelected) {
      // Deseleccionar
      const newSelection = selectedTenantIds.filter(id => id !== tenantId);
      onSelectionChange(newSelection);

      // Si era primario, asignar otro o null
      if (primaryTenantId === tenantId) {
        onPrimaryChange(newSelection[0] || null);
      }
    } else {
      // Seleccionar
      if (maxSelections && selectedTenantIds.length >= maxSelections) {
        return; // No permitir más selecciones
      }

      const newSelection = [...selectedTenantIds, tenantId];
      onSelectionChange(newSelection);

      // Si es el primero, marcarlo como primario
      if (selectedTenantIds.length === 0) {
        onPrimaryChange(tenantId);
      }
    }
  };

  const handleRemoveTenant = (tenantId: string) => {
    const newSelection = selectedTenantIds.filter(id => id !== tenantId);
    onSelectionChange(newSelection);

    if (primaryTenantId === tenantId) {
      onPrimaryChange(newSelection[0] || null);
    }
  };

  const handleSetPrimary = (tenantId: string) => {
    if (selectedTenantIds.includes(tenantId)) {
      onPrimaryChange(tenantId);
    }
  };

  const getSelectedTenantsInfo = () => {
    return selectedTenantIds
      .map(id => tenantsCache.get(String(id)))
      .filter(Boolean) as TenantInfo[];
  };

  const selectedTenantsInfo = getSelectedTenantsInfo();

  // Debug logs
  console.log('TenantMultiSelector - Debug:', {
    selectedTenantIds,
    selectedTenantIdsTypes: selectedTenantIds.map(id => typeof id),
    selectedTenantsLength: selectedTenants.length,
    cacheSize: tenantsCache.size,
    selectedTenantsInfoLength: selectedTenantsInfo.length,
    cacheKeys: Array.from(tenantsCache.keys()),
    cacheKeysTypes: Array.from(tenantsCache.keys()).map(k => typeof k),
    selectedTenantsIds: selectedTenants.map(t => ({ id: t.id, type: typeof t.id }))
  });

  return (
    <div className="space-y-3">
      {/* Selector con búsqueda */}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            variant="outline"
            role="combobox"
            aria-expanded={open}
            disabled={disabled}
            className={`w-full justify-between ${error ? 'border-red-500' : ''}`}
          >
            <div className="flex items-center gap-2">
              <Building2 className="h-4 w-4 text-gray-500" />
              <span className="text-gray-700">
                {selectedTenantIds.length > 0
                  ? `${selectedTenantIds.length} organización(es) seleccionada(s)`
                  : placeholder}
              </span>
            </div>
            <ChevronDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-[800px] p-0" align="start">
          <Command shouldFilter={false}>
            <CommandInput
              placeholder="Buscar por nombre o RUC..."
              value={searchQuery}
              onValueChange={setSearchQuery}
              className="h-12 text-base"
            />
            <CommandList>
              <CommandEmpty>
                {isSearching ? (
                  <div className="flex items-center justify-center py-6">
                    <Loader2 className="h-4 w-4 animate-spin text-gray-400" />
                    <span className="ml-2 text-sm text-gray-500">Buscando...</span>
                  </div>
                ) : searchQuery && results.length === 0 ? (
                  <div className="py-6 text-center text-sm text-gray-500">
                    No se encontraron organizaciones
                  </div>
                ) : null}
              </CommandEmpty>

              {/* Mostrar tenants seleccionados cuando no hay búsqueda o resultados */}
              {displayTenants.length > 0 ? (
                <CommandGroup>
                  <ScrollArea className="h-[300px]">
                    {displayTenants.map((tenant) => {
                      const isSelected = selectedTenantIds.includes(String(tenant.id));
                      const isPrimary = primaryTenantId === String(tenant.id);

                      return (
                        <CommandItem
                          key={tenant.id}
                          value={String(tenant.id)}
                          onSelect={() => handleToggleTenant(String(tenant.id), tenant)}
                          className="flex items-center gap-2 px-2 py-3"
                        >
                          <Checkbox
                            checked={isSelected}
                            onCheckedChange={() => handleToggleTenant(String(tenant.id), tenant)}
                          />
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2">
                              <span className="font-medium truncate">{tenant.name}</span>
                              {isPrimary && (
                                <Badge
                                  variant="secondary"
                                  className="bg-blue-100 text-blue-800 text-xs"
                                >
                                  <Star className="h-3 w-3 mr-1" />
                                  Primaria
                                </Badge>
                              )}
                            </div>
                            <p className="text-sm text-gray-500">RUC: {tenant.ruc}</p>
                          </div>
                        </CommandItem>
                      );
                    })}

                    {/* Botón cargar más */}
                    {hasMore && (
                      <div className="p-2">
                        <Button
                          variant="ghost"
                          className="w-full"
                          onClick={loadMore}
                          disabled={isSearching}
                        >
                          {isSearching ? (
                            <>
                              <Loader2 className="h-4 w-4 animate-spin mr-2" />
                              Cargando...
                            </>
                          ) : (
                            'Cargar más resultados'
                          )}
                        </Button>
                      </div>
                    )}
                  </ScrollArea>
                </CommandGroup>
              ) : null}
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>

      {/* Error message */}
      {error && <p className="text-sm text-red-500">{error}</p>}

      {/* Tags de organizaciones seleccionadas */}
      {selectedTenantsInfo.length > 0 && (
        <div className="space-y-2">
          <p className="text-sm font-medium text-gray-700">
            Organizaciones seleccionadas ({selectedTenantsInfo.length})
            {minSelections > 0 && ` - Mínimo: ${minSelections}`}
          </p>
          <div className="space-y-2">
            {selectedTenantsInfo.map((tenant) => {
              const isPrimary = primaryTenantId === String(tenant.id);

              return (
                <div
                  key={tenant.id}
                  className={`
                    flex items-center justify-between p-3 rounded-lg border
                    ${isPrimary ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'}
                  `}
                >
                  <div className="flex items-center gap-3 flex-1 min-w-0">
                    <Building2 className="h-4 w-4 text-gray-400 shrink-0" />
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <span className="font-medium truncate">{tenant.name}</span>
                        {isPrimary && (
                          <Badge className="bg-blue-600 text-white text-xs shrink-0">
                            <Star className="h-3 w-3 mr-1 fill-white" />
                            Primaria
                          </Badge>
                        )}
                      </div>
                      <p className="text-sm text-gray-500">RUC: {tenant.ruc}</p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 shrink-0">
                    {!isPrimary && selectedTenantIds.length >= 1 && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => handleSetPrimary(String(tenant.id))}
                        className="text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                      >
                        <Star className="h-3 w-3 mr-1" />
                        Marcar primaria
                      </Button>
                    )}
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={() => handleRemoveTenant(String(tenant.id))}
                      disabled={disabled}
                      className="h-8 w-8 text-gray-400 hover:text-red-600 hover:bg-red-50"
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Info sobre mínimo de selecciones */}
      {minSelections > 0 && selectedTenantIds.length < minSelections && (
        <p className="text-sm text-amber-600">
          Debes seleccionar al menos {minSelections} organización(es)
        </p>
      )}
    </div>
  );
}
