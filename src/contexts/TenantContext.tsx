import React, { createContext, useContext, useState, ReactNode } from "react";

export interface TenantBranding {
  primaryColor: string;
  secondaryColor: string;
  logoUrl: string | null;
  faviconUrl: string | null;
}

export interface TenantInfo {
  id: string;
  name: string;
  ruc: string;
  address: string;
  phone: string;
  email: string;
  branding: TenantBranding;
}

interface TenantContextType {
  tenant: TenantInfo;
  updateTenantInfo: (info: Partial<TenantInfo>) => void;
  updateBranding: (branding: Partial<TenantBranding>) => void;
}

// Default tenant configuration
const defaultTenant: TenantInfo = {
  id: "miboleta",
  name: "Miboleta",
  ruc: "20123456789",
  address: "Av. Principal 123, Lima, Perú",
  phone: "+51 1 234 5678",
  email: "contacto@miboleta.com",
  branding: {
    primaryColor: "#2563EB",
    secondaryColor: "#1E40AF",
    logoUrl: null,
    faviconUrl: null,
  },
};

const TenantContext = createContext<TenantContextType | undefined>(undefined);

export function TenantProvider({ children }: { children: ReactNode }) {
  const [tenant, setTenant] = useState<TenantInfo>(defaultTenant);

  const updateTenantInfo = (info: Partial<TenantInfo>) => {
    setTenant((prev) => ({
      ...prev,
      ...info,
    }));
  };

  const updateBranding = (branding: Partial<TenantBranding>) => {
    setTenant((prev) => ({
      ...prev,
      branding: {
        ...prev.branding,
        ...branding,
      },
    }));
  };

  return (
    <TenantContext.Provider
      value={{
        tenant,
        updateTenantInfo,
        updateBranding,
      }}
    >
      {children}
    </TenantContext.Provider>
  );
}

export function useTenant() {
  const context = useContext(TenantContext);
  if (context === undefined) {
    throw new Error("useTenant must be used within a TenantProvider");
  }
  return context;
}
