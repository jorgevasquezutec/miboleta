/**
 * Mock API Services
 * Simula peticiones HTTP con setTimeout para simular latencia de red
 * Fácilmente reemplazable con fetch/axios real más adelante
 */

// Simular delay de red (300-800ms)
const simulateNetworkDelay = () => {
  const delay = Math.random() * 500 + 300;
  return new Promise((resolve) => setTimeout(resolve, delay));
};

// Tipos
export interface User {
  id: string;
  name: string;
  email: string;
  role: "platform-admin" | "tenant-admin" | "manager" | "employee";
  tenantId?: string;
  status: "active" | "inactive";
  department?: string;
}

export interface Tenant {
  id: string;
  name: string;
  ruc: string;
  status: "active" | "inactive";
  primaryColor?: string;
  createdAt: string;
}

export interface Document {
  id: string;
  title: string;
  category: "payslip" | "contract" | "certificate";
  status: "signed" | "pending" | "expired";
  date: string;
}

export interface LoginResponse {
  user: User;
  token: string;
}

export interface ApiResponse<T> {
  data: T;
  message?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  page: number;
  pageSize: number;
  totalItems: number;
  totalPages: number;
}

// ============================================================================
// MOCK DATA
// ============================================================================

const MOCK_USERS: User[] = [
  {
    id: "user-1",
    name: "Admin Plataforma",
    email: "platform@miboleta.com",
    role: "platform-admin",
    status: "active",
  },
  {
    id: "user-2",
    name: "Carlos Ruiz",
    email: "carlos@empresa1.com",
    role: "tenant-admin",
    tenantId: "miboleta",
    status: "active",
    department: "Administración",
  },
  {
    id: "user-3",
    name: "María García",
    email: "maria@empresa1.com",
    role: "manager",
    tenantId: "miboleta",
    status: "active",
    department: "Recursos Humanos",
  },
  {
    id: "user-4",
    name: "Juan Pérez",
    email: "juan@empresa1.com",
    role: "employee",
    tenantId: "miboleta",
    status: "active",
    department: "Ventas",
  },
];

const MOCK_TENANTS: Tenant[] = [
  {
    id: "miboleta",
    name: "MiBoleta",
    ruc: "20123456789",
    status: "active",
    primaryColor: "#2563EB",
    createdAt: "2024-01-15",
  },
  {
    id: "empresa2",
    name: "Empresa Demo",
    ruc: "20987654321",
    status: "active",
    primaryColor: "#10B981",
    createdAt: "2024-02-20",
  },
];

// Generar documentos mock
const generateMockDocuments = (): Document[] => {
  const docs: Document[] = [];
  const titles = ["Boleta de Pago", "Contrato de Trabajo", "Certificado Laboral", "Anexo", "Constancia"];
  const categories: Document["category"][] = ["payslip", "contract", "certificate"];
  const statuses: Document["status"][] = ["signed", "pending", "expired"];
  const months = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

  for (let i = 0; i < 50; i++) {
    docs.push({
      id: `doc-${i + 1}`,
      title: `${titles[i % titles.length]} - ${months[i % months.length]} 2025`,
      category: categories[i % categories.length],
      status: statuses[i % statuses.length],
      date: `${(i % 28) + 1} ${months[i % months.length]} 2025`,
    });
  }

  return docs;
};

const MOCK_DOCUMENTS = generateMockDocuments();

// ============================================================================
// API SERVICES
// ============================================================================

export const mockApi = {
  // AUTH
  async login(email: string, password: string): Promise<LoginResponse> {
    await simulateNetworkDelay();

    const user = MOCK_USERS.find((u) => u.email.toLowerCase() === email.toLowerCase());

    if (!user) {
      // Fallback mock user
      const mockUser: User = {
        id: "user-mock",
        name: "Usuario Demo",
        email,
        role: email.includes("admin") ? "tenant-admin" : "employee",
        tenantId: "miboleta",
        status: "active",
      };

      return {
        user: mockUser,
        token: `mock-token-${Date.now()}`,
      };
    }

    return {
      user,
      token: `mock-token-${Date.now()}`,
    };
  },

  async logout(): Promise<void> {
    await simulateNetworkDelay();
    // Simular logout
  },

  // USERS
  async getUsers(tenantId?: string): Promise<User[]> {
    await simulateNetworkDelay();

    if (tenantId) {
      return MOCK_USERS.filter((u) => u.tenantId === tenantId);
    }

    return [...MOCK_USERS];
  },

  async createUser(userData: Omit<User, "id">): Promise<User> {
    await simulateNetworkDelay();

    const newUser: User = {
      ...userData,
      id: `user-${Date.now()}`,
    };

    MOCK_USERS.push(newUser);
    return newUser;
  },

  async updateUser(id: string, updates: Partial<User>): Promise<User> {
    await simulateNetworkDelay();

    const index = MOCK_USERS.findIndex((u) => u.id === id);
    if (index === -1) throw new Error("User not found");

    MOCK_USERS[index] = { ...MOCK_USERS[index], ...updates };
    return MOCK_USERS[index];
  },

  async deleteUser(id: string): Promise<void> {
    await simulateNetworkDelay();

    const index = MOCK_USERS.findIndex((u) => u.id === id);
    if (index !== -1) {
      MOCK_USERS.splice(index, 1);
    }
  },

  // TENANTS
  async getTenants(): Promise<Tenant[]> {
    await simulateNetworkDelay();
    return [...MOCK_TENANTS];
  },

  async createTenant(tenantData: Omit<Tenant, "id" | "createdAt">): Promise<Tenant> {
    await simulateNetworkDelay();

    const newTenant: Tenant = {
      ...tenantData,
      id: `tenant-${Date.now()}`,
      createdAt: new Date().toISOString().split("T")[0],
    };

    MOCK_TENANTS.push(newTenant);
    return newTenant;
  },

  async updateTenant(id: string, updates: Partial<Tenant>): Promise<Tenant> {
    await simulateNetworkDelay();

    const index = MOCK_TENANTS.findIndex((t) => t.id === id);
    if (index === -1) throw new Error("Tenant not found");

    MOCK_TENANTS[index] = { ...MOCK_TENANTS[index], ...updates };
    return MOCK_TENANTS[index];
  },

  async deleteTenant(id: string): Promise<void> {
    await simulateNetworkDelay();

    const index = MOCK_TENANTS.findIndex((t) => t.id === id);
    if (index !== -1) {
      MOCK_TENANTS.splice(index, 1);
    }
  },

  // DOCUMENTS
  async getDocuments(params?: {
    page?: number;
    pageSize?: number;
    search?: string;
    status?: string;
    category?: string;
  }): Promise<PaginatedResponse<Document>> {
    await simulateNetworkDelay();

    let filtered = [...MOCK_DOCUMENTS];

    // Apply filters
    if (params?.search) {
      filtered = filtered.filter((doc) =>
        doc.title.toLowerCase().includes(params.search!.toLowerCase())
      );
    }

    if (params?.status && params.status !== "all") {
      filtered = filtered.filter((doc) => doc.status === params.status);
    }

    if (params?.category && params.category !== "all") {
      filtered = filtered.filter((doc) => doc.category === params.category);
    }

    // Pagination
    const page = params?.page || 1;
    const pageSize = params?.pageSize || 10;
    const startIndex = (page - 1) * pageSize;
    const endIndex = startIndex + pageSize;

    const paginatedData = filtered.slice(startIndex, endIndex);

    return {
      data: paginatedData,
      page,
      pageSize,
      totalItems: filtered.length,
      totalPages: Math.ceil(filtered.length / pageSize),
    };
  },

  async getDocumentById(id: string): Promise<Document> {
    await simulateNetworkDelay();

    const doc = MOCK_DOCUMENTS.find((d) => d.id === id);
    if (!doc) throw new Error("Document not found");

    return doc;
  },
};
