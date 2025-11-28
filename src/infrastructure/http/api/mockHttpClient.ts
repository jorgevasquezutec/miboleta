import { User, Tenant, Document } from '@/core/domain/entities';
import { PAGINATION } from '@/shared/config';
import { delay } from '@/shared/utils';

/**
 * Cliente HTTP Mock
 * Simula peticiones HTTP con datos en memoria
 * TODO: Reemplazar con cliente HTTP real (axios/fetch) conectado a Laravel
 */

// ============================================================================
// MOCK DATA
// ============================================================================

const MOCK_USERS: User[] = [
  {
    id: "user-1",
    name: "Admin Plataforma",
    email: "platform@miboleta.com",
    role: "platform_admin",
    tenantId: null,
    status: "active",
    createdAt: new Date('2024-01-01'),
    updatedAt: new Date('2024-01-01'),
  },
  {
    id: "user-2",
    name: "Carlos Ruiz",
    email: "carlos@empresa1.com",
    role: "tenant_admin",
    tenantId: "tenant-1",
    status: "active",
    createdAt: new Date('2024-01-15'),
    updatedAt: new Date('2024-01-15'),
  },
  {
    id: "user-3",
    name: "María García",
    email: "maria@empresa1.com",
    role: "employee",
    tenantId: "tenant-1",
    status: "active",
    createdAt: new Date('2024-02-01'),
    updatedAt: new Date('2024-02-01'),
  },
];

const MOCK_TENANTS: Tenant[] = [
  {
    id: "tenant-1",
    name: "MiBoleta SAC",
    ruc: "20123456789",
    address: "Av. Principal 123, Lima",
    status: "active",
    primaryColor: "#2563EB",
    secondaryColor: "#1E40AF",
    logo: "/logos/miboleta.png",
    subscriptionPlan: "premium",
    maxUsers: 100,
    maxStorage: 10000,
    createdAt: new Date('2024-01-01'),
    updatedAt: new Date('2024-01-01'),
  },
];

const MOCK_DOCUMENTS: Document[] = [
  {
    id: "doc-1",
    title: "Boleta de Pago - Enero 2025",
    category: "payslip",
    status: "signed",
    fileUrl: "/documents/boleta-ene-2025.pdf",
    fileName: "boleta-ene-2025.pdf",
    fileSize: 245678,
    mimeType: "application/pdf",
    uploadedBy: "user-2",
    tenantId: "tenant-1",
    metadata: { month: "Enero", year: "2025" },
    createdAt: new Date('2025-01-05'),
    updatedAt: new Date('2025-01-05'),
  },
  {
    id: "doc-2",
    title: "Contrato de Trabajo",
    category: "contract",
    status: "pending",
    fileUrl: "/documents/contrato.pdf",
    fileName: "contrato.pdf",
    fileSize: 512000,
    mimeType: "application/pdf",
    uploadedBy: "user-2",
    tenantId: "tenant-1",
    createdAt: new Date('2025-01-10'),
    updatedAt: new Date('2025-01-10'),
  },
];

// ============================================================================
// HTTP CLIENT
// ============================================================================

interface HttpResponse<T> {
  data: T;
  status: number;
  statusText: string;
}

/**
 * Cliente HTTP Mock
 */
export const mockApi = {
  /**
   * GET request
   */
  async get<T>(url: string): Promise<HttpResponse<T>> {
    await delay(PAGINATION.MOCK_DELAY);

    // Parse URL
    const [path, queryString] = url.split('?');
    const params = new URLSearchParams(queryString);

    // Route matching
    if (path === '/users') {
      return {
        data: [...MOCK_USERS] as T,
        status: 200,
        statusText: 'OK',
      };
    }

    if (path.startsWith('/users/')) {
      const id = path.split('/')[2];
      const user = MOCK_USERS.find(u => u.id === id);
      if (!user) throw new Error('User not found');
      return { data: user as T, status: 200, statusText: 'OK' };
    }

    if (path === '/tenants') {
      return {
        data: [...MOCK_TENANTS] as T,
        status: 200,
        statusText: 'OK',
      };
    }

    if (path.startsWith('/tenants/')) {
      const id = path.split('/')[2];
      const tenant = MOCK_TENANTS.find(t => t.id === id);
      if (!tenant) throw new Error('Tenant not found');
      return { data: tenant as T, status: 200, statusText: 'OK' };
    }

    if (path === '/documents') {
      // Aplicar filtros y paginación
      let filtered = [...MOCK_DOCUMENTS];

      const search = params.get('search');
      if (search) {
        filtered = filtered.filter(doc =>
          doc.title.toLowerCase().includes(search.toLowerCase())
        );
      }

      const category = params.get('category');
      if (category) {
        filtered = filtered.filter(doc => doc.category === category);
      }

      const status = params.get('status');
      if (status) {
        filtered = filtered.filter(doc => doc.status === status);
      }

      const tenantId = params.get('tenantId');
      if (tenantId) {
        filtered = filtered.filter(doc => doc.tenantId === tenantId);
      }

      // Paginación
      const page = parseInt(params.get('page') || '1');
      const limit = parseInt(params.get('limit') || String(PAGINATION.DEFAULT_PAGE_SIZE));
      const startIndex = (page - 1) * limit;
      const endIndex = startIndex + limit;

      const paginatedData = filtered.slice(startIndex, endIndex);

      return {
        data: {
          data: paginatedData,
          page,
          limit,
          total: filtered.length,
          totalPages: Math.ceil(filtered.length / limit),
        } as T,
        status: 200,
        statusText: 'OK',
      };
    }

    if (path.startsWith('/documents/')) {
      const id = path.split('/')[2];
      const doc = MOCK_DOCUMENTS.find(d => d.id === id);
      if (!doc) throw new Error('Document not found');
      return { data: doc as T, status: 200, statusText: 'OK' };
    }

    throw new Error(`Mock API: Ruta no implementada: ${url}`);
  },

  /**
   * POST request
   */
  async post<T>(url: string, data: unknown): Promise<HttpResponse<T>> {
    await delay(PAGINATION.MOCK_DELAY);

    if (url === '/users') {
      const newUser: User = {
        ...(data as Omit<User, 'id' | 'createdAt' | 'updatedAt'>),
        id: `user-${Date.now()}`,
        createdAt: new Date(),
        updatedAt: new Date(),
      };
      MOCK_USERS.push(newUser);
      return { data: newUser as T, status: 201, statusText: 'Created' };
    }

    if (url === '/tenants') {
      const newTenant: Tenant = {
        ...(data as Omit<Tenant, 'id' | 'createdAt' | 'updatedAt'>),
        id: `tenant-${Date.now()}`,
        createdAt: new Date(),
        updatedAt: new Date(),
      };
      MOCK_TENANTS.push(newTenant);
      return { data: newTenant as T, status: 201, statusText: 'Created' };
    }

    if (url === '/documents') {
      const newDoc: Document = {
        ...(data as Omit<Document, 'id' | 'createdAt' | 'updatedAt'>),
        id: `doc-${Date.now()}`,
        createdAt: new Date(),
        updatedAt: new Date(),
      };
      MOCK_DOCUMENTS.push(newDoc);
      return { data: newDoc as T, status: 201, statusText: 'Created' };
    }

    throw new Error(`Mock API: Ruta no implementada: ${url}`);
  },

  /**
   * PUT request
   */
  async put<T>(url: string, data: unknown): Promise<HttpResponse<T>> {
    await delay(PAGINATION.MOCK_DELAY);

    if (url.startsWith('/users/')) {
      const id = url.split('/')[2];
      const index = MOCK_USERS.findIndex(u => u.id === id);
      if (index === -1) throw new Error('User not found');

      MOCK_USERS[index] = {
        ...MOCK_USERS[index],
        ...(data as Partial<User>),
        updatedAt: new Date(),
      };
      return { data: MOCK_USERS[index] as T, status: 200, statusText: 'OK' };
    }

    if (url.startsWith('/tenants/')) {
      const id = url.split('/')[2];
      const index = MOCK_TENANTS.findIndex(t => t.id === id);
      if (index === -1) throw new Error('Tenant not found');

      MOCK_TENANTS[index] = {
        ...MOCK_TENANTS[index],
        ...(data as Partial<Tenant>),
        updatedAt: new Date(),
      };
      return { data: MOCK_TENANTS[index] as T, status: 200, statusText: 'OK' };
    }

    if (url.startsWith('/documents/')) {
      const id = url.split('/')[2];
      const index = MOCK_DOCUMENTS.findIndex(d => d.id === id);
      if (index === -1) throw new Error('Document not found');

      MOCK_DOCUMENTS[index] = {
        ...MOCK_DOCUMENTS[index],
        ...(data as Partial<Document>),
        updatedAt: new Date(),
      };
      return { data: MOCK_DOCUMENTS[index] as T, status: 200, statusText: 'OK' };
    }

    throw new Error(`Mock API: Ruta no implementada: ${url}`);
  },

  /**
   * DELETE request
   */
  async delete(url: string): Promise<HttpResponse<void>> {
    await delay(PAGINATION.MOCK_DELAY);

    if (url.startsWith('/users/')) {
      const id = url.split('/')[2];
      const index = MOCK_USERS.findIndex(u => u.id === id);
      if (index === -1) throw new Error('User not found');
      MOCK_USERS.splice(index, 1);
      return { data: undefined as void, status: 204, statusText: 'No Content' };
    }

    if (url.startsWith('/tenants/')) {
      const id = url.split('/')[2];
      const index = MOCK_TENANTS.findIndex(t => t.id === id);
      if (index === -1) throw new Error('Tenant not found');
      MOCK_TENANTS.splice(index, 1);
      return { data: undefined as void, status: 204, statusText: 'No Content' };
    }

    if (url.startsWith('/documents/')) {
      const id = url.split('/')[2];
      const index = MOCK_DOCUMENTS.findIndex(d => d.id === id);
      if (index === -1) throw new Error('Document not found');
      MOCK_DOCUMENTS.splice(index, 1);
      return { data: undefined as void, status: 204, statusText: 'No Content' };
    }

    throw new Error(`Mock API: Ruta no implementada: ${url}`);
  },
};
