# Clean Architecture - MiBoleta Frontend

## 📁 Estructura del Proyecto

```
src/
├── core/                          # 🎯 CAPA DE DOMINIO
│   ├── domain/
│   │   ├── entities/             # Entidades de negocio
│   │   │   ├── User.ts
│   │   │   ├── Tenant.ts
│   │   │   └── Document.ts
│   │   ├── repositories/         # Interfaces (Ports)
│   │   │   ├── IUserRepository.ts
│   │   │   ├── IDocumentRepository.ts
│   │   │   └── ITenantRepository.ts
│   │   └── use-cases/            # Lógica de negocio
│   │       ├── auth/
│   │       │   └── LoginUseCase.ts
│   │       ├── users/
│   │       ├── documents/
│   │       └── tenants/
│   │
│   └── application/              # 🔧 CAPA DE APLICACIÓN
│       ├── services/             # Servicios de aplicación
│       ├── dtos/                 # Data Transfer Objects
│       └── ports/                # Puertos de entrada/salida

├── infrastructure/               # 🔌 CAPA DE INFRAESTRUCTURA
│   ├── http/
│   │   ├── api/                  # Cliente HTTP (Mock/Real)
│   │   │   └── mockApi.ts
│   │   └── interceptors/         # Interceptores HTTP
│   ├── persistence/
│   │   ├── repositories/         # Implementaciones de repos
│   │   └── mappers/              # DTO ↔ Entity mappers
│   └── storage/                  # LocalStorage/SessionStorage

├── presentation/                 # 🎨 CAPA DE PRESENTACIÓN
│   ├── pages/                    # Páginas (ex-Views)
│   │   ├── auth/
│   │   │   ├── LoginPage.tsx
│   │   │   └── index.ts
│   │   ├── admin/
│   │   │   ├── DashboardPage.tsx
│   │   │   ├── TenantsPage.tsx
│   │   │   ├── UsersPage.tsx
│   │   │   ├── SettingsPage.tsx
│   │   │   └── index.ts
│   │   └── employee/
│   │       ├── DashboardPage.tsx
│   │       └── index.ts
│   │
│   ├── components/               # Componentes React
│   │   ├── common/              # Compartidos
│   │   │   ├── StatsCard.tsx
│   │   │   ├── MultitenantInfo.tsx
│   │   │   └── index.ts
│   │   ├── layout/              # Layouts
│   │   │   ├── Navbar.tsx
│   │   │   └── index.ts
│   │   ├── features/            # Por feature
│   │   │   ├── auth/
│   │   │   ├── documents/
│   │   │   │   ├── DocumentCard.tsx
│   │   │   │   ├── DocumentUploadZone.tsx
│   │   │   │   └── index.ts
│   │   │   ├── tenants/
│   │   │   └── users/
│   │   └── ui/                  # shadcn/ui
│   │
│   ├── hooks/                   # Custom React hooks
│   ├── stores/                  # Zustand stores
│   │   ├── authStore.ts
│   │   ├── usersStore.ts
│   │   ├── documentsStore.ts
│   │   ├── tenantsStore.ts
│   │   └── index.ts
│   └── routes/                  # Configuración de rutas

├── shared/                      # 🔄 CÓDIGO COMPARTIDO
│   ├── constants/              # Constantes
│   │   └── index.ts
│   ├── types/                  # Types globales
│   ├── utils/                  # Utilidades
│   │   └── index.ts
│   └── config/                 # Configuración
│       └── index.ts

├── assets/                     # 📦 Assets estáticos
└── styles/                     # 🎨 Estilos globales
```

## 🎯 Principios de Clean Architecture

### 1. **Independencia de Frameworks**
- El dominio no conoce React, Zustand, o cualquier otro framework
- Se puede cambiar React por Vue sin tocar el dominio

### 2. **Testeable**
- Cada capa se puede testear independientemente
- Los use cases no dependen de la UI ni de la infraestructura

### 3. **Independencia de UI**
- La lógica de negocio está separada de la presentación
- Se puede cambiar la UI sin afectar el dominio

### 4. **Independencia de BD**
- Se usan interfaces (repositories) en vez de implementaciones
- Fácil cambiar de localStorage a API real

### 5. **Inversión de Dependencias**
- Las capas externas dependen de las internas
- El dominio no depende de nada

## 📚 Capas

### 🎯 Core/Domain (Capa de Dominio)
**Responsabilidad:** Lógica de negocio pura

- **Entities:** Modelos de negocio (User, Document, Tenant)
- **Repositories:** Interfaces (contratos)
- **Use Cases:** Casos de uso (LoginUseCase, CreateUserUseCase)

**Reglas:**
- ❌ NO puede importar de otras capas
- ✅ Solo TypeScript puro
- ✅ Sin dependencias de frameworks

### 🔧 Core/Application (Capa de Aplicación)
**Responsabilidad:** Orquestación

- **Services:** Coordinan múltiples use cases
- **DTOs:** Objetos de transferencia
- **Ports:** Interfaces para comunicación

### 🔌 Infrastructure (Capa de Infraestructura)
**Responsabilidad:** Implementaciones concretas

- **HTTP:** Cliente API (mock o real)
- **Repositories:** Implementaciones de las interfaces
- **Mappers:** Conversión DTO ↔ Entity
- **Storage:** LocalStorage, SessionStorage

**Reglas:**
- ✅ Implementa las interfaces del dominio
- ✅ Aquí va fetch, axios, localStorage

### 🎨 Presentation (Capa de Presentación)
**Responsabilidad:** UI y UX

- **Pages:** Páginas principales (antes Views)
- **Components:** Componentes React organizados
- **Hooks:** Custom hooks
- **Stores:** Zustand (state management)
- **Routes:** React Router

**Reglas:**
- ✅ Usa los use cases del dominio
- ✅ Solo se preocupa de renderizar

### 🔄 Shared (Compartido)
**Responsabilidad:** Código reutilizable

- **Constants:** Constantes globales
- **Types:** Types/interfaces compartidos
- **Utils:** Funciones utilitarias
- **Config:** Configuración de la app

## 🚀 Cómo Usar

### Importar desde las capas

```tsx
// ✅ CORRECTO: Importar desde barrels
import { User, CreateUserData } from '@/core/domain/entities';
import { LoginUseCase } from '@/core/domain/use-cases/auth';
import { useAuthStore } from '@/presentation/stores';
import { formatDate, isValidEmail } from '@/shared/utils';
import { USER_ROLES } from '@/shared/constants';

// ❌ INCORRECTO: Importar directamente
import { User } from '@/core/domain/entities/User';
```

### Crear un nuevo feature

1. **Entidad** en `core/domain/entities/`
2. **Repository Interface** en `core/domain/repositories/`
3. **Use Cases** en `core/domain/use-cases/`
4. **Repository Implementation** en `infrastructure/persistence/repositories/`
5. **Store** en `presentation/stores/`
6. **Components** en `presentation/components/features/`
7. **Page** en `presentation/pages/`

### Ejemplo: Agregar "Facturas"

```
1. src/core/domain/entities/Invoice.ts
2. src/core/domain/repositories/IInvoiceRepository.ts
3. src/core/domain/use-cases/invoices/CreateInvoiceUseCase.ts
4. src/infrastructure/persistence/repositories/InvoiceRepository.ts
5. src/presentation/stores/invoicesStore.ts
6. src/presentation/components/features/invoices/InvoiceCard.tsx
7. src/presentation/pages/admin/InvoicesPage.tsx
```

## 🔄 Migración desde la estructura anterior

### Antes
```
src/
├── components/
│   ├── views/          → presentation/pages/
│   ├── ui/             → presentation/components/ui/
│   ├── Navbar.tsx      → presentation/components/layout/
│   └── DocumentCard.tsx → presentation/components/features/documents/
├── contexts/           → [ELIMINADO] Ahora usamos Zustand
├── stores/             → presentation/stores/
├── hooks/              → presentation/hooks/
└── services/           → infrastructure/http/api/
```

### Ahora
```
src/
├── core/              [NUEVO] Lógica de negocio
├── infrastructure/    [NUEVO] Implementaciones
├── presentation/      [NUEVO] UI organizada
└── shared/           [NUEVO] Código compartido
```

## 📖 Documentos relacionados

- `ZUSTAND_MIGRATION_GUIDE.md` - Migración de Context a Zustand
- `vite.config.ts` - Configuración de Vite con alias

## 🎓 Recursos

- [Clean Architecture (Uncle Bob)](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [Hexagonal Architecture](https://alistair.cockburn.us/hexagonal-architecture/)
- [Domain-Driven Design](https://www.domainlanguage.com/ddd/)
