import { useState } from 'react';
import { useTenantSearch } from '@/presentation/hooks/useTenantSearch';
import { Tenant } from '@/core/domain/entities/Tenant';
import { Button } from '@/presentation/components/ui/button';
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
import { Building2, Loader2, ChevronDown, Check, X } from 'lucide-react';
import { cn } from '@/presentation/components/ui/utils';

export interface TenantAutocompleteSelectorProps {
    /** ID del tenant seleccionado */
    value: string | null;
    /** Callback cuando cambia la selección */
    onChange: (tenantId: string | null) => void;
    /** Tenant seleccionado (para mostrar info completa) */
    selectedTenant?: Tenant | null;
    /** Texto del placeholder */
    placeholder?: string;
    /** Deshabilitado */
    disabled?: boolean;
    /** Clase CSS adicional */
    className?: string;
}

/**
 * Selector de tenant con autocompletado y búsqueda server-side
 */
export function TenantAutocompleteSelector({
    value,
    onChange,
    selectedTenant,
    placeholder = 'Buscar organización...',
    disabled = false,
    className,
}: TenantAutocompleteSelectorProps) {
    const [open, setOpen] = useState(false);

    const { searchQuery, setSearchQuery, results, isSearching, hasMore, loadMore } =
        useTenantSearch();

    const handleSelect = (tenant: Tenant) => {
        onChange(tenant.id);
        setOpen(false);
        setSearchQuery('');
    };

    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange(null);
        setSearchQuery('');
    };

    const displayName = selectedTenant?.name || (value ? 'Cargando...' : placeholder);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className={cn("w-full justify-between", className)}
                >
                    <div className="flex items-center gap-2 flex-1 min-w-0">
                        <Building2 className="h-4 w-4 text-gray-500 flex-shrink-0" />
                        <span className={cn(
                            "truncate",
                            value ? "text-gray-900" : "text-gray-500"
                        )}>
                            {displayName}
                        </span>
                    </div>
                    <div className="flex items-center gap-1 flex-shrink-0">
                        {value && (
                            <div
                                role="button"
                                tabIndex={0}
                                className="p-0.5 rounded hover:bg-gray-100 cursor-pointer"
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                }}
                                onClick={handleClear}
                            >
                                <X className="h-3.5 w-3.5 text-gray-400 hover:text-gray-600" />
                            </div>
                        )}
                        <ChevronDown className="h-4 w-4 opacity-50" />
                    </div>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[400px] p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput
                        placeholder="Buscar por nombre o RUC..."
                        value={searchQuery}
                        onValueChange={setSearchQuery}
                        className="h-11"
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
                            ) : (
                                <div className="py-6 text-center text-sm text-gray-500">
                                    Escribe para buscar...
                                </div>
                            )}
                        </CommandEmpty>

                        {results.length > 0 && (
                            <CommandGroup>
                                <ScrollArea className="h-[250px]">
                                    {results.map((tenant) => {
                                        const isSelected = value === tenant.id;

                                        return (
                                            <CommandItem
                                                key={tenant.id}
                                                value={tenant.id}
                                                onSelect={() => handleSelect(tenant)}
                                                className="flex items-center gap-2 px-3 py-2 cursor-pointer"
                                            >
                                                <Check
                                                    className={cn(
                                                        "h-4 w-4 flex-shrink-0",
                                                        isSelected ? "opacity-100" : "opacity-0"
                                                    )}
                                                />
                                                <div className="flex-1 min-w-0">
                                                    <div className="font-medium truncate">{tenant.name}</div>
                                                    <div className="text-xs text-gray-500">RUC: {tenant.ruc}</div>
                                                </div>
                                            </CommandItem>
                                        );
                                    })}

                                    {/* Botón cargar más */}
                                    {hasMore && (
                                        <div className="p-2">
                                            <Button
                                                variant="ghost"
                                                className="w-full text-sm"
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
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
