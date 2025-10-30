import React, { createContext, useContext, useEffect, useState, ReactNode } from "react";
import { v4 as uuidv4 } from "uuid";

export type UserRole = "platform-admin" | "tenant-admin" | "manager" | "employee";

export interface Tenant {
  id: string;
  name: string;
  ruc: string;
  status: "active" | "inactive";
  primaryColor?: string;
  createdAt: string;
}

export interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  tenantId?: string; // undefined for platform-admin
  status?: "active" | "inactive";
  department?: string;
}

interface PlatformContextType {
  tenants: Tenant[];
  users: User[];
  createTenant: (t: Omit<Tenant, "id" | "createdAt">) => Tenant;
  updateTenant: (id: string, patch: Partial<Tenant>) => void;
  deleteTenant: (id: string) => void;
  createUser: (u: Omit<User, "id">) => User;
  updateUser: (id: string, patch: Partial<User>) => void;
  deleteUser: (id: string) => void;
  getUsersByTenant: (tenantId?: string) => User[];
}

const PLATFORM_TENANTS_KEY = "miboleta_tenants";
const PLATFORM_USERS_KEY = "miboleta_users";

const PlatformContext = createContext<PlatformContextType | undefined>(undefined);

const initialTenants: Tenant[] = [
  {
    id: "miboleta",
    name: "MiBoleta",
    ruc: "20123456789",
    status: "active",
    primaryColor: "#2563EB",
    createdAt: "2024-01-15",
  },
  {
    id: "tech-solutions",
    name: "Tech Solutions S.A.",
    ruc: "20987654321",
    status: "active",
    primaryColor: "#10B981",
    createdAt: "2024-02-20",
  },
  {
    id: "innovate-corp",
    name: "Innovate Corp",
    ruc: "20555666777",
    status: "inactive",
    primaryColor: "#F59E0B",
    createdAt: "2024-03-10",
  },
];

const initialUsers: User[] = [
  {
    id: uuidv4(),
    name: "Platform Admin",
    email: "platform@miboleta.com",
    role: "platform-admin",
    status: "active",
  },
  {
    id: uuidv4(),
    name: "Carlos Ruiz",
    email: "carlos.ruiz@miboleta.com",
    role: "tenant-admin",
    tenantId: "miboleta",
    status: "active",
  },
  {
    id: uuidv4(),
    name: "María García",
    email: "maria.garcia@tech-solutions.com",
    role: "tenant-admin",
    tenantId: "tech-solutions",
    status: "active",
  },
  // employees
  {
    id: uuidv4(),
    name: "Ana Martínez",
    email: "ana.martinez@miboleta.com",
    role: "employee",
    tenantId: "miboleta",
    status: "active",
  },
  {
    id: uuidv4(),
    name: "Luis Torres",
    email: "luis.torres@tech-solutions.com",
    role: "employee",
    tenantId: "tech-solutions",
    status: "active",
  },
];

export function PlatformProvider({ children }: { children: ReactNode }) {
  const [tenants, setTenants] = useState<Tenant[]>(() => {
    try {
      const raw = localStorage.getItem(PLATFORM_TENANTS_KEY);
      return raw ? JSON.parse(raw) : initialTenants;
    } catch (e) {
      return initialTenants;
    }
  });

  const [users, setUsers] = useState<User[]>(() => {
    try {
      const raw = localStorage.getItem(PLATFORM_USERS_KEY);
      return raw ? JSON.parse(raw) : initialUsers;
    } catch (e) {
      return initialUsers;
    }
  });

  useEffect(() => {
    localStorage.setItem(PLATFORM_TENANTS_KEY, JSON.stringify(tenants));
  }, [tenants]);

  useEffect(() => {
    localStorage.setItem(PLATFORM_USERS_KEY, JSON.stringify(users));
  }, [users]);

  const createTenant = (t: Omit<Tenant, "id" | "createdAt">) => {
    const newTenant: Tenant = {
      id: t.name.toLowerCase().replace(/\s+/g, "-"),
      name: t.name,
      ruc: t.ruc,
      status: t.status || "active",
      primaryColor: t.primaryColor || "#2563EB",
      createdAt: new Date().toISOString().split("T")[0],
    };
    setTenants((prev) => [...prev, newTenant]);
    return newTenant;
  };

  const updateTenant = (id: string, patch: Partial<Tenant>) => {
    setTenants((prev) => prev.map((t) => (t.id === id ? { ...t, ...patch } : t)));
  };

  const deleteTenant = (id: string) => {
    setTenants((prev) => prev.filter((t) => t.id !== id));
    // also remove tenantId from users
    setUsers((prev) => prev.map((u) => (u.tenantId === id ? { ...u, tenantId: undefined } : u)));
  };

  const createUser = (u: Omit<User, "id">) => {
    const newUser: User = {
      id: uuidv4(),
      name: u.name,
      email: u.email,
      role: u.role,
      tenantId: u.tenantId,
      status: u.status || "active",
    };
    setUsers((prev) => [...prev, newUser]);
    return newUser;
  };

  const updateUser = (id: string, patch: Partial<User>) => {
    setUsers((prev) => prev.map((u) => (u.id === id ? { ...u, ...patch } : u)));
  };

  const deleteUser = (id: string) => {
    setUsers((prev) => prev.filter((u) => u.id !== id));
  };

  const getUsersByTenant = (tenantId?: string) => {
    if (!tenantId) return users;
    return users.filter((u) => u.tenantId === tenantId);
  };

  return (
    <PlatformContext.Provider
      value={{
        tenants,
        users,
        createTenant,
        updateTenant,
        deleteTenant,
        createUser,
        updateUser,
        deleteUser,
        getUsersByTenant,
      }}
    >
      {children}
    </PlatformContext.Provider>
  );
}

export function usePlatform() {
  const ctx = useContext(PlatformContext);
  if (!ctx) throw new Error("usePlatform must be used within PlatformProvider");
  return ctx;
}

export default PlatformContext;
