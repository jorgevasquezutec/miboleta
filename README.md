# MiBoleta - Sistema de Gestión Documental

Sistema multi-tenant de gestión de documentos para empresas. Basado en el diseño original de Figma disponible en https://www.figma.com/design/7tyJxcpdePhaIBf7fxyVwF/Create-Mockups-for-MiBoleta.

## Características

- Sistema multi-tenant con aislamiento de datos por empresa
- Gestión de usuarios con roles (Platform Admin, Tenant Admin, Manager, Employee)
- Gestión de documentos con búsqueda, filtros y paginación
- Dashboard con métricas y estadísticas
- Visor de documentos integrado
- Interfaz responsive construida con React y Tailwind CSS
- Gestión de estado con Zustand
- Routing con React Router v7
- Componentes UI de shadcn/ui

## Roles de Usuario

- **Platform Admin**: Administrador de la plataforma, puede gestionar todas las empresas y usuarios
- **Tenant Admin**: Administrador de empresa, puede gestionar usuarios de su empresa
- **Manager**: Gestor con permisos intermedios
- **Employee**: Usuario final con acceso a documentos

## Instalación

1. Instalar dependencias:
```bash
npm install
```

2. Iniciar el servidor de desarrollo:
```bash
npm run dev
```

La aplicación estará disponible en `http://localhost:3000`

## Scripts Disponibles

- `npm run dev` - Inicia el servidor de desarrollo
- `npm run build` - Construye la aplicación para producción
- `npm run deploy` - Despliega la aplicación a GitHub Pages

## Deployment

La aplicación está configurada para desplegarse en GitHub Pages. Ver [DEPLOYMENT.md](DEPLOYMENT.md) para instrucciones detalladas.

### Deployment Automático

Cada push a la rama `main` despliega automáticamente la aplicación usando GitHub Actions.

### Deployment Manual

```bash
npm run deploy
```

## Tecnologías

- **React 18** - Biblioteca UI
- **TypeScript** - Tipado estático
- **Vite** - Build tool y dev server
- **React Router v7** - Routing
- **Zustand** - Gestión de estado
- **Tailwind CSS** - Estilos
- **shadcn/ui** - Componentes UI
- **Lucide React** - Iconos
- **Recharts** - Gráficos
- **Sonner** - Notificaciones toast

## Estructura del Proyecto

```
src/
├── components/
│   ├── ui/              # Componentes UI de shadcn
│   └── views/           # Vistas principales
├── contexts/            # Contextos de React
├── hooks/               # Custom hooks
├── services/            # API mock y servicios
├── stores/              # Stores de Zustand
└── utils/               # Utilidades
```

## Documentación Adicional

- [Guía de Paginación](TABLE_PAGINATION.md) - Documentación del sistema de paginación
- [Guía de Migración a Zustand](ZUSTAND_MIGRATION_GUIDE.md) - Migración de Context a Zustand
- [Guía de Deployment](DEPLOYMENT.md) - Instrucciones de deployment a GitHub Pages

## Desarrollo

### Mock API

El proyecto utiliza un sistema de API mock con `setTimeout` para simular latencia de red (300-800ms). Ver `src/services/mockApi.ts`.

### Stores de Zustand

- `authStore` - Autenticación y usuario actual
- `usersStore` - Gestión de usuarios
- `tenantsStore` - Gestión de empresas/tenants
- `documentsStore` - Gestión de documentos con paginación

### Migración a API Real

Para migrar a una API real, reemplaza las llamadas en `src/services/mockApi.ts` con llamadas `fetch` reales. Los stores ya están preparados para trabajar con APIs reales.

## Licencia

Este proyecto es privado y confidencial.
