import { useAuthStore } from '@/presentation/stores';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/presentation/components/ui/select';
import { Building2 } from 'lucide-react';

export interface TenantSelectorProps {
  /** ID del tenant seleccionado */
  value: string;
  /** Callback cuando cambia la selección */
  onValueChange: (tenantId: string) => void;
  /** Texto del placeholder */
  placeholder?: string;
  /** Deshabilitado */
  disabled?: boolean;
}

/**
 * Selector simple de tenant para usuarios multi-tenant
 * Muestra solo los tenants del usuario actual
 */
export function TenantSelector({
  value,
  onValueChange,
  placeholder = 'Selecciona una empresa',
  disabled = false,
}: TenantSelectorProps) {
  const { user } = useAuthStore();

  // Si el usuario no tiene tenants, no mostrar nada
  if (!user?.tenants || user.tenants.length === 0) {
    return null;
  }

  return (
    <Select value={value} onValueChange={onValueChange} disabled={disabled}>
      <SelectTrigger className="w-full">
        <div className="flex items-center gap-2">
          <Building2 className="w-4 h-4 text-gray-500" />
          <SelectValue placeholder={placeholder} />
        </div>
      </SelectTrigger>
      <SelectContent>
        {user.tenants.map((tenant) => (
          <SelectItem key={tenant.id} value={String(tenant.id)}>
            <div className="flex items-center gap-2">
              <Building2 className="w-4 h-4 text-gray-500" />
              <div>
                <div className="font-medium">{tenant.name}</div>
                {tenant.ruc && (
                  <div className="text-xs text-gray-500">RUC: {tenant.ruc}</div>
                )}
              </div>
            </div>
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
