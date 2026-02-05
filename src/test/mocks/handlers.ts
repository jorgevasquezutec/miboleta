import { http, HttpResponse } from 'msw';

const API_BASE = '/api';

// Mock user data
export const mockUser = {
  id: '1',
  name: 'Juan',
  last_name: 'Perez',
  full_name: 'Juan Perez',
  email: 'juan@example.com',
  avatar_url: null,
  document_type: 'dni',
  document_text: '12345678',
  phone: '999888777',
  status: 'active',
  must_change_password: false,
  role: 'admin',
  roles: ['admin'],
  tenants: [
    {
      id: '1',
      name: 'Empresa Test',
      ruc: '12345678901',
      logo_url: null,
      is_primary: true,
      supervisor_id: null,
    },
  ],
  primary_tenant: {
    id: '1',
    name: 'Empresa Test',
    ruc: '12345678901',
  },
  created_at: '2024-01-01T00:00:00.000Z',
  updated_at: '2024-01-01T00:00:00.000Z',
};

export const mockTenant = {
  id: '1',
  name: 'Empresa Test',
  ruc: '12345678901',
  logo_url: null,
  status: 'active',
  created_at: '2024-01-01T00:00:00.000Z',
  updated_at: '2024-01-01T00:00:00.000Z',
};

export const mockDocument = {
  id: '1',
  file_path: 'documents/test.pdf',
  original_name: 'test.pdf',
  status: 'pending',
  period: '2024-01',
  employee_document_number: '12345678',
  user_id: '1',
  tenant_id: '1',
  doc_type_id: '1',
  created_at: '2024-01-01T00:00:00.000Z',
  updated_at: '2024-01-01T00:00:00.000Z',
};

export const mockVacationRequest = {
  id: '1',
  user_id: '1',
  tenant_id: '1',
  start_date: '2024-02-01',
  end_date: '2024-02-05',
  days_requested: 5,
  reason: 'Vacaciones familiares',
  status: 'pending',
  approved_by: null,
  rejected_by: null,
  rejection_reason: null,
  created_at: '2024-01-15T00:00:00.000Z',
  updated_at: '2024-01-15T00:00:00.000Z',
};

export const handlers = [
  // Auth endpoints
  http.get(`${API_BASE}/sanctum/csrf-cookie`, () => {
    return new HttpResponse(null, { status: 204 });
  }),

  http.post(`${API_BASE}/auth/login`, async ({ request }) => {
    const body = await request.json() as { email: string; password: string };

    if (body.email === 'invalid@test.com') {
      return HttpResponse.json(
        { message: 'Invalid credentials' },
        { status: 401 }
      );
    }

    return HttpResponse.json({
      user: mockUser,
      message: 'Login successful',
    });
  }),

  http.post(`${API_BASE}/auth/logout`, () => {
    return HttpResponse.json({ message: 'Logged out successfully' });
  }),

  http.get(`${API_BASE}/auth/me`, () => {
    return HttpResponse.json({ user: mockUser });
  }),

  http.post(`${API_BASE}/auth/refresh`, () => {
    return HttpResponse.json({ user: mockUser });
  }),

  // Users endpoints
  http.get(`${API_BASE}/users`, () => {
    return HttpResponse.json({
      data: [mockUser],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 1,
      },
    });
  }),

  http.get(`${API_BASE}/users/:id`, () => {
    return HttpResponse.json({ data: mockUser });
  }),

  http.post(`${API_BASE}/users`, async ({ request }) => {
    const body = await request.json();
    return HttpResponse.json({
      data: { ...mockUser, ...body, id: '2' },
      message: 'User created successfully',
    });
  }),

  http.put(`${API_BASE}/users/:id`, async ({ request }) => {
    const body = await request.json();
    return HttpResponse.json({
      data: { ...mockUser, ...body },
      message: 'User updated successfully',
    });
  }),

  http.delete(`${API_BASE}/users/:id`, () => {
    return HttpResponse.json({ message: 'User deleted successfully' });
  }),

  // Tenants endpoints
  http.get(`${API_BASE}/tenants`, () => {
    return HttpResponse.json({
      data: [mockTenant],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 1,
      },
    });
  }),

  http.get(`${API_BASE}/tenants/:id`, () => {
    return HttpResponse.json({ data: mockTenant });
  }),

  // Documents endpoints
  http.get(`${API_BASE}/documents`, () => {
    return HttpResponse.json({
      data: [mockDocument],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 1,
      },
    });
  }),

  http.get(`${API_BASE}/documents/:id`, () => {
    return HttpResponse.json({ data: mockDocument });
  }),

  // Vacation endpoints
  http.get(`${API_BASE}/vacations`, () => {
    return HttpResponse.json({
      data: [mockVacationRequest],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 1,
      },
    });
  }),

  http.get(`${API_BASE}/vacations/my-requests`, () => {
    return HttpResponse.json({
      data: [mockVacationRequest],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 1,
      },
    });
  }),

  http.post(`${API_BASE}/vacations`, async ({ request }) => {
    const body = await request.json();
    return HttpResponse.json({
      data: { ...mockVacationRequest, ...body, id: '2' },
      message: 'Vacation request created successfully',
    });
  }),

  // Profile endpoints
  http.get(`${API_BASE}/profile`, () => {
    return HttpResponse.json({ user: mockUser });
  }),

  http.put(`${API_BASE}/profile`, async ({ request }) => {
    const body = await request.json();
    return HttpResponse.json({
      user: { ...mockUser, ...body },
      message: 'Profile updated successfully',
    });
  }),

  // Notifications endpoints
  http.get(`${API_BASE}/notifications`, () => {
    return HttpResponse.json({
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
      },
    });
  }),

  http.get(`${API_BASE}/notifications/unread-count`, () => {
    return HttpResponse.json({ count: 0 });
  }),
];
