# TASK: Testing Frontend con Vitest

**Fecha:** 2025-12-15  
**Estado:** Pendiente  
**Prioridad:** Alta

---

## Objetivo

Implementar tests unitarios y de integración para el frontend usando Vitest y React Testing Library.

---

## Fase 1: Configuración de Vitest

### 1.1 Instalar dependencias

```bash
npm install -D vitest @testing-library/react @testing-library/jest-dom @testing-library/user-event jsdom @vitest/coverage-v8
```

### 1.2 Crear archivo de configuración `vitest.config.ts`

```typescript
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      exclude: ['node_modules/', 'src/test/'],
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
});
```

### 1.3 Crear archivo de setup `src/test/setup.ts`

```typescript
import '@testing-library/jest-dom';
import { vi } from 'vitest';

// Mock de matchMedia para componentes que lo usan
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
});

// Mock de ResizeObserver
global.ResizeObserver = vi.fn().mockImplementation(() => ({
  observe: vi.fn(),
  unobserve: vi.fn(),
  disconnect: vi.fn(),
}));
```

### 1.4 Agregar scripts a `package.json`

```json
{
  "scripts": {
    "test": "vitest",
    "test:run": "vitest run",
    "test:coverage": "vitest run --coverage"
  }
}
```

---

## Fase 2: Tests de Componentes UI

### 2.1 Tests de Button (`src/presentation/components/ui/button.test.tsx`)

```typescript
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import { Button } from './button';

describe('Button', () => {
  it('renders with children', () => {
    render(<Button>Click me</Button>);
    expect(screen.getByRole('button')).toHaveTextContent('Click me');
  });

  it('calls onClick when clicked', async () => {
    const handleClick = vi.fn();
    render(<Button onClick={handleClick}>Click me</Button>);
    
    await userEvent.click(screen.getByRole('button'));
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('is disabled when disabled prop is true', () => {
    render(<Button disabled>Click me</Button>);
    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('applies variant classes correctly', () => {
    render(<Button variant="destructive">Delete</Button>);
    expect(screen.getByRole('button')).toHaveClass('bg-destructive');
  });
});
```

### 2.2 Tests de Input (`src/presentation/components/ui/input.test.tsx`)

```typescript
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect } from 'vitest';
import { Input } from './input';

describe('Input', () => {
  it('renders with placeholder', () => {
    render(<Input placeholder="Enter text" />);
    expect(screen.getByPlaceholderText('Enter text')).toBeInTheDocument();
  });

  it('handles value changes', async () => {
    render(<Input />);
    const input = screen.getByRole('textbox');
    
    await userEvent.type(input, 'Hello');
    expect(input).toHaveValue('Hello');
  });

  it('is disabled when disabled prop is true', () => {
    render(<Input disabled />);
    expect(screen.getByRole('textbox')).toBeDisabled();
  });
});
```

---

## Fase 3: Tests de Hooks

### 3.1 Tests de useUrlFilters (`src/presentation/hooks/useUrlFilters.test.ts`)

```typescript
import { renderHook, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { BrowserRouter } from 'react-router-dom';
import { useUrlFilters } from './useUrlFilters';

// Wrapper con Router
const wrapper = ({ children }: { children: React.ReactNode }) => (
  <BrowserRouter>{children}</BrowserRouter>
);

describe('useUrlFilters', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/');
  });

  it('returns default values initially', () => {
    const { result } = renderHook(
      () => useUrlFilters({
        defaultValues: { page: 1, search: '' }
      }),
      { wrapper }
    );

    expect(result.current.filters.page).toBe(1);
    expect(result.current.filters.search).toBe('');
  });

  it('updates URL when setFilters is called', () => {
    const { result } = renderHook(
      () => useUrlFilters({
        defaultValues: { search: '' }
      }),
      { wrapper }
    );

    act(() => {
      result.current.setFilters({ search: 'test' });
    });

    expect(result.current.filters.search).toBe('test');
  });

  it('resets filters correctly', () => {
    const { result } = renderHook(
      () => useUrlFilters({
        defaultValues: { page: 1, search: '' }
      }),
      { wrapper }
    );

    act(() => {
      result.current.setFilters({ search: 'test', page: 5 });
    });

    act(() => {
      result.current.resetFilters();
    });

    expect(result.current.filters.page).toBe(1);
    expect(result.current.filters.search).toBe('');
  });
});
```

---

## Fase 4: Tests de Stores (Zustand)

### 4.1 Tests de authStore (`src/presentation/stores/authStore.test.ts`)

```typescript
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useAuthStore } from './authStore';

// Mock del repositorio
vi.mock('@/infrastructure/persistence/repositories/AuthRepository', () => ({
  authRepository: {
    login: vi.fn(),
    logout: vi.fn(),
    getUser: vi.fn(),
  }
}));

describe('authStore', () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: null,
      isAuthenticated: false,
      isLoading: false,
      error: null,
    });
  });

  it('initial state is correct', () => {
    const state = useAuthStore.getState();
    expect(state.user).toBeNull();
    expect(state.isAuthenticated).toBe(false);
  });

  it('setUser updates user and isAuthenticated', () => {
    const mockUser = { id: 1, email: 'test@test.com', name: 'Test' };
    
    useAuthStore.getState().setUser(mockUser);
    
    const state = useAuthStore.getState();
    expect(state.user).toEqual(mockUser);
    expect(state.isAuthenticated).toBe(true);
  });

  it('logout clears user', () => {
    useAuthStore.setState({ user: { id: 1 }, isAuthenticated: true });
    
    useAuthStore.getState().clearAuth();
    
    const state = useAuthStore.getState();
    expect(state.user).toBeNull();
    expect(state.isAuthenticated).toBe(false);
  });
});
```

---

## Fase 5: Tests de Páginas

### 5.1 Tests de LoginView (`src/presentation/pages/auth/LoginView.test.tsx`)

```typescript
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import { BrowserRouter } from 'react-router-dom';
import LoginView from './LoginView';

// Mock de hooks
vi.mock('@/presentation/hooks/useAuth', () => ({
  useAuth: () => ({
    login: vi.fn().mockResolvedValue(undefined),
    isLoading: false,
  }),
}));

const renderWithRouter = (component: React.ReactElement) => {
  return render(<BrowserRouter>{component}</BrowserRouter>);
};

describe('LoginView', () => {
  it('renders login form', () => {
    renderWithRouter(<LoginView />);
    
    expect(screen.getByLabelText(/correo/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/contraseña/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /iniciar sesión/i })).toBeInTheDocument();
  });

  it('shows forgot password link', () => {
    renderWithRouter(<LoginView />);
    
    expect(screen.getByText(/olvidaste tu contraseña/i)).toBeInTheDocument();
  });

  it('allows user to fill form', async () => {
    renderWithRouter(<LoginView />);
    
    const emailInput = screen.getByLabelText(/correo/i);
    const passwordInput = screen.getByLabelText(/contraseña/i);
    
    await userEvent.type(emailInput, 'test@test.com');
    await userEvent.type(passwordInput, 'password123');
    
    expect(emailInput).toHaveValue('test@test.com');
    expect(passwordInput).toHaveValue('password123');
  });
});
```

### 5.2 Tests de NotFoundPage (`src/presentation/pages/NotFoundPage.test.tsx`)

```typescript
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi } from 'vitest';
import { BrowserRouter } from 'react-router-dom';
import { NotFoundPage } from './NotFoundPage';

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

describe('NotFoundPage', () => {
  it('renders 404 message', () => {
    render(<BrowserRouter><NotFoundPage /></BrowserRouter>);
    
    expect(screen.getByText('404')).toBeInTheDocument();
    expect(screen.getByText(/página no encontrada/i)).toBeInTheDocument();
  });

  it('has back and home buttons', () => {
    render(<BrowserRouter><NotFoundPage /></BrowserRouter>);
    
    expect(screen.getByText(/volver atrás/i)).toBeInTheDocument();
    expect(screen.getByText(/ir al inicio/i)).toBeInTheDocument();
  });

  it('navigates home when home button clicked', async () => {
    render(<BrowserRouter><NotFoundPage /></BrowserRouter>);
    
    await userEvent.click(screen.getByText(/ir al inicio/i));
    expect(mockNavigate).toHaveBeenCalledWith('/');
  });
});
```

---

## Fase 6: Tests de Utilidades

### 6.1 Tests de formatters (`src/presentation/utils/formatters.test.ts`)

```typescript
import { describe, it, expect } from 'vitest';
import { formatDate, formatDateTime, formatCurrency } from './formatters';

describe('formatDate', () => {
  it('formats date correctly', () => {
    const result = formatDate('2025-12-15');
    expect(result).toContain('15');
    expect(result).toContain('diciembre');
    expect(result).toContain('2025');
  });

  it('returns empty string for null', () => {
    expect(formatDate(null)).toBe('');
  });
});

describe('formatDateTime', () => {
  it('formats date with time', () => {
    const result = formatDateTime('2025-12-15T14:30:00');
    expect(result).toContain('15');
    expect(result).toContain('14:30');
  });
});

describe('formatCurrency', () => {
  it('formats currency correctly', () => {
    const result = formatCurrency(1234.56);
    expect(result).toContain('1');
    expect(result).toContain('234');
  });
});
```

---

## Fase 7: Ejecutar y Verificar

### 7.1 Ejecutar todos los tests

```bash
npm run test
```

### 7.2 Ejecutar con coverage

```bash
npm run test:coverage
```

### 7.3 Ejecutar tests específicos

```bash
npm run test -- button
npm run test -- authStore
```

---

## Criterios de Aceptación

- [ ] Vitest configurado correctamente
- [ ] Al menos 80% de cobertura en utilidades
- [ ] Tests para todos los componentes UI base
- [ ] Tests para hooks principales
- [ ] Tests para stores de Zustand
- [ ] CI/CD ejecuta tests automáticamente

---

## Prioridad de Tests

1. **Alta:** Utilidades (formatters, validators)
2. **Alta:** Hooks (useUrlFilters, useAuth)
3. **Media:** Stores (authStore, documentsStore)
4. **Media:** Componentes UI base
5. **Baja:** Páginas completas (requieren más mocks)

---

## Notas

- Usar `vi.mock()` para mockear módulos
- Usar `vi.fn()` para mockear funciones
- Usar `renderHook` de Testing Library para hooks
- Configurar mocks globales en `setup.ts`

*Última actualización: 2025-12-15*
