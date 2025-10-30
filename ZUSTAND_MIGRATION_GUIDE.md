# Guía de Migración a Zustand

## 📦 ¿Qué se ha implementado?

Se ha migrado de React Context a **Zustand** para state management con las siguientes mejoras:

✅ **Mock API Services** que simulan peticiones HTTP con `setTimeout`
✅ **Auth Store** - Autenticación con login/logout
✅ **Documents Store** - Documentos con paginación y filtros
✅ **Users Store** - CRUD de usuarios
✅ **Tenants Store** - CRUD de empresas
✅ **Persistencia** - Los datos se guardan en localStorage
✅ **TypeScript** - Todo tipado correctamente

## 🚀 Ventajas

### Antes (Context)
```tsx
// Múltiples providers anidados
<PlatformProvider>
  <TenantProvider>
    <App />
  </TenantProvider>
</PlatformProvider>

// Uso en componentes
const { users, createUser } = usePlatform();
```

### Ahora (Zustand)
```tsx
// Sin providers necesarios
<App />

// Uso en componentes
const { users, createUser, isLoading } = useUsersStore();
```

**Beneficios:**
- ✅ Menos código boilerplate
- ✅ Mejor rendimiento (solo re-renderiza lo necesario)
- ✅ Estados de carga incluidos
- ✅ Manejo de errores incluido
- ✅ Más fácil de testear
- ✅ Simula peticiones HTTP reales

## 📖 Cómo Usar

### 1. Auth Store

```tsx
import { useAuthStore } from "./stores";

function LoginView() {
  const { user, login, logout, isLoading, error } = useAuthStore();

  const handleLogin = async () => {
    try {
      await login("user@example.com", "password");
      // Simula: POST /api/login
      // Usuario guardado en localStorage
    } catch (error) {
      console.error(error);
    }
  };

  return (
    <div>
      {isLoading && <p>Cargando...</p>}
      {error && <p>Error: {error}</p>}
      {user ? (
        <button onClick={logout}>Cerrar Sesión</button>
      ) : (
        <button onClick={handleLogin}>Iniciar Sesión</button>
      )}
    </div>
  );
}
```

### 2. Documents Store (con Paginación)

```tsx
import { useDocumentsStore } from "./stores";
import { useEffect } from "react";

function EmployeeDashboardView() {
  const {
    documents,
    isLoading,
    page,
    totalPages,
    searchTerm,
    setSearchTerm,
    setPage,
    fetchDocuments,
  } = useDocumentsStore();

  // Cargar documentos al montar
  useEffect(() => {
    fetchDocuments();
    // Simula: GET /api/documents?page=1&pageSize=6
  }, []);

  return (
    <div>
      {/* Búsqueda */}
      <input
        value={searchTerm}
        onChange={(e) => setSearchTerm(e.target.value)}
        // Automáticamente hace fetch con el nuevo término
      />

      {/* Lista */}
      {isLoading ? (
        <p>Cargando...</p>
      ) : (
        documents.map((doc) => <div key={doc.id}>{doc.title}</div>)
      )}

      {/* Paginación */}
      <button onClick={() => setPage(page - 1)} disabled={page === 1}>
        Anterior
      </button>
      <span>Página {page} de {totalPages}</span>
      <button onClick={() => setPage(page + 1)} disabled={page === totalPages}>
        Siguiente
      </button>
    </div>
  );
}
```

### 3. Users Store

```tsx
import { useUsersStore } from "./stores";

function UserManagementView() {
  const { users, createUser, updateUser, deleteUser, isLoading } = useUsersStore();

  useEffect(() => {
    fetchUsers(); // Simula: GET /api/users
  }, []);

  const handleCreate = async () => {
    await createUser({
      name: "Nuevo Usuario",
      email: "nuevo@example.com",
      role: "employee",
      tenantId: "miboleta",
      status: "active",
    });
    // Simula: POST /api/users
  };

  const handleUpdate = async (id: string) => {
    await updateUser(id, { name: "Nombre Actualizado" });
    // Simula: PUT /api/users/:id
  };

  const handleDelete = async (id: string) => {
    await deleteUser(id);
    // Simula: DELETE /api/users/:id
  };

  return (
    <div>
      {isLoading && <p>Cargando...</p>}
      {users.map((user) => (
        <div key={user.id}>
          {user.name}
          <button onClick={() => handleUpdate(user.id)}>Editar</button>
          <button onClick={() => handleDelete(user.id)}>Eliminar</button>
        </div>
      ))}
      <button onClick={handleCreate}>Crear Usuario</button>
    </div>
  );
}
```

### 4. Tenants Store

```tsx
import { useTenantsStore } from "./stores";

function TenantManagementView() {
  const { tenants, createTenant, updateTenant, deleteTenant } = useTenantsStore();

  useEffect(() => {
    fetchTenants(); // Simula: GET /api/tenants
  }, []);

  const handleCreate = async () => {
    await createTenant({
      name: "Nueva Empresa",
      ruc: "20123456789",
      status: "active",
      primaryColor: "#2563EB",
    });
    // Simula: POST /api/tenants
  };

  return (
    <div>
      {tenants.map((tenant) => (
        <div key={tenant.id}>{tenant.name}</div>
      ))}
    </div>
  );
}
```

## 🔄 Migrar de Context a Zustand

### Paso 1: Remover Providers

**Antes:**
```tsx
// App.tsx
<PlatformProvider>
  <TenantProvider>
    <AppContent />
  </TenantProvider>
</PlatformProvider>
```

**Después:**
```tsx
// App.tsx
<AppContent /> // No providers necesarios
```

### Paso 2: Actualizar Imports

**Antes:**
```tsx
import { usePlatform } from "./contexts/PlatformContext";

const { users, createUser } = usePlatform();
```

**Después:**
```tsx
import { useUsersStore } from "./stores";

const { users, createUser, isLoading } = useUsersStore();
```

### Paso 3: Agregar useEffect para cargar datos

```tsx
import { useEffect } from "react";

useEffect(() => {
  fetchUsers(); // Cargar datos al montar
}, []);
```

## 🌐 Migrar a API Real

Cuando estés listo para usar APIs reales, solo necesitas cambiar el archivo `mockApi.ts`:

**Antes (Mock):**
```ts
// mockApi.ts
export const mockApi = {
  async getUsers() {
    await simulateNetworkDelay();
    return [...MOCK_USERS];
  },
};
```

**Después (Real):**
```ts
// realApi.ts
export const api = {
  async getUsers() {
    const response = await fetch("/api/users");
    return response.json();
  },
};
```

Y actualizar los stores:
```ts
// usersStore.ts
import { api } from "../services/realApi"; // Cambiar import

// ... resto del código igual
const users = await api.getUsers(); // Misma interfaz
```

## 📝 Referencia Completa

### Auth Store
| Método | Descripción | Simula |
|--------|-------------|--------|
| `login(email, password)` | Iniciar sesión | `POST /api/login` |
| `logout()` | Cerrar sesión | `POST /api/logout` |
| `updateProfile(updates)` | Actualizar perfil | `PUT /api/profile` |

### Documents Store
| Método | Descripción | Simula |
|--------|-------------|--------|
| `fetchDocuments(params)` | Obtener documentos paginados | `GET /api/documents?page=1&search=...` |
| `fetchDocumentById(id)` | Obtener documento por ID | `GET /api/documents/:id` |
| `setSearchTerm(term)` | Buscar documentos | Auto-fetch con query |
| `setStatusFilter(status)` | Filtrar por estado | Auto-fetch con query |
| `setCategoryFilter(cat)` | Filtrar por categoría | Auto-fetch con query |
| `setPage(page)` | Cambiar página | Auto-fetch con nueva página |
| `setPageSize(size)` | Cambiar tamaño | Auto-fetch con nuevo tamaño |

### Users Store
| Método | Descripción | Simula |
|--------|-------------|--------|
| `fetchUsers(tenantId?)` | Obtener usuarios | `GET /api/users` |
| `createUser(data)` | Crear usuario | `POST /api/users` |
| `updateUser(id, updates)` | Actualizar usuario | `PUT /api/users/:id` |
| `deleteUser(id)` | Eliminar usuario | `DELETE /api/users/:id` |
| `getUsersByTenant(id)` | Filtrar por tenant | Cliente-side filter |

### Tenants Store
| Método | Descripción | Simula |
|--------|-------------|--------|
| `fetchTenants()` | Obtener empresas | `GET /api/tenants` |
| `createTenant(data)` | Crear empresa | `POST /api/tenants` |
| `updateTenant(id, updates)` | Actualizar empresa | `PUT /api/tenants/:id` |
| `deleteTenant(id)` | Eliminar empresa | `DELETE /api/tenants/:id` |

## 🎯 Próximos Pasos

1. ✅ Migrar cada vista para usar los stores
2. ✅ Agregar estados de carga en la UI
3. ✅ Manejar errores con toasts
4. ✅ Testear flujos completos
5. 🔄 Cuando estés listo, reemplazar mockApi con fetch real

## 💡 Tips

- Los stores persisten datos en localStorage automáticamente
- `isLoading` está disponible en todos los stores
- `error` se limpia automáticamente o con `clearError()`
- El delay de red (300-800ms) simula latencia real
- Todos los métodos son async y retornan Promises
