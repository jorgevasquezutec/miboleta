import React from 'react';
import { Check, ChevronsUpDown, Building2 } from 'lucide-react';
import { useAuth } from '@/presentation/hooks/useAuth';
import { Button } from '@/presentation/components/ui/button';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/presentation/components/ui/command';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/presentation/components/ui/popover';
import { cn } from '@/shared/utils';

/**
 * Componente para cambiar entre tenants
 * Solo se muestra si el usuario tiene múltiples tenants
 */
export const TenantSwitcher: React.FC = () => {
  const { currentTenant, getTenants, hasMultipleTenants, switchTenant, isRoot } = useAuth();
  const [open, setOpen] = React.useState(false);

  const tenants = getTenants();

  // No mostrar si el usuario es root (sin tenants) o tiene un solo tenant
  if (isRoot() || !hasMultipleTenants()) {
    return null;
  }

  const handleSelectTenant = (tenantId: string) => {
    if (tenantId !== currentTenant?.id) {
      switchTenant(tenantId);
      // Podríamos recargar la página o actualizar datos aquí
      window.location.reload();
    }
    setOpen(false);
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          aria-label="Seleccionar organización"
          className="w-[250px] justify-between"
        >
          <div className="flex items-center gap-2">
            <Building2 className="h-4 w-4 shrink-0 opacity-50" />
            <span className="truncate">
              {currentTenant?.name || 'Seleccionar organización'}
            </span>
          </div>
          <ChevronsUpDown className="ml-auto h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-[250px] p-0">
        <Command>
          <CommandInput placeholder="Buscar organización..." />
          <CommandList>
            <CommandEmpty>No se encontró organización.</CommandEmpty>
            <CommandGroup heading="Organizaciones">
              {tenants.map((tenant) => (
                <CommandItem
                  key={tenant.id}
                  value={tenant.id}
                  onSelect={() => handleSelectTenant(tenant.id)}
                  className="cursor-pointer"
                >
                  <div className="flex items-center gap-2 flex-1">
                    <Building2 className="h-4 w-4 shrink-0" />
                    <div className="flex flex-col">
                      <span className="font-medium">{tenant.name}</span>
                      <span className="text-xs text-muted-foreground">
                        RUC: {tenant.ruc}
                      </span>
                    </div>
                  </div>
                  {tenant.is_primary && (
                    <span className="ml-2 text-xs bg-primary/10 text-primary px-2 py-0.5 rounded">
                      Principal
                    </span>
                  )}
                  <Check
                    className={cn(
                      'ml-auto h-4 w-4',
                      currentTenant?.id === tenant.id ? 'opacity-100' : 'opacity-0'
                    )}
                  />
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
};
