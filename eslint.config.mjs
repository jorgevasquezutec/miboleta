import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';

/**
 * ESLint del frontend.
 *
 * Existe por un motivo concreto: dos bugs de React que costaron un debug caro y
 * que estas reglas cazan al escribirlos.
 *
 *   1. Un `useAuthStore(s => new Set(...))` en RootLayout: el selector devolvía
 *      un valor nuevo en cada pasada, zustand lo comparaba con Object.is y React
 *      abortaba con "Maximum update depth exceeded" en TODAS las páginas.
 *   2. Un `if (!user) return null` por encima de dos useEffect en ese mismo
 *      layout: al hacer logout React veía menos hooks que en el render anterior.
 *
 * `tsc --noEmit` (npm run typecheck) sigue siendo quien valida los tipos: aquí
 * NO se duplica lo que ya reporta (p. ej. variables sin usar), para que el ruido
 * no entierre lo que sí importa.
 *
 * Criterio de severidad: error = rompe la app en runtime; warn = deuda real pero
 * demasiado extendida en el código actual como para bloquear CI hoy.
 */
export default tseslint.config(
  {
    ignores: [
      'dist/**',
      'node_modules/**',
      'coverage/**',
      // Proyecto Laravel aparte; se valida con php artisan test.
      'backend/**',
      'public/**',
      'signer/**',
      // Scripts sueltos de Node para generar documentación, no son la app.
      'docs/**',
    ],
  },

  js.configs.recommended,
  ...tseslint.configs.recommended,

  {
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.es2022,
      },
    },
    plugins: {
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      // ── Lo que motivó todo esto ─────────────────────────────────────────
      // Un hook detrás de un return temprano/condición rompe el render.
      'react-hooks/rules-of-hooks': 'error',
      // setState directo en render/efecto: el bucle infinito de manual.
      'react-hooks/set-state-in-render': 'error',
      // Deps incompletas: causa habitual de efectos en bucle o datos rancios.
      // warn: el código actual arrastra bastantes y no queremos bloquear CI.
      'react-hooks/exhaustive-deps': 'warn',

      // Selector de zustand que construye un valor NUEVO en cada pasada.
      //
      // Ninguna regla de react-hooks caza esto: `useAuthStore(fn)` es un hook
      // normal para el linter. Pero zustand compara lo que devuelve el selector
      // con Object.is, así que `new Set(...)`, `{...}` o `[...]` nunca son
      // iguales al valor anterior -> useSyncExternalStore cree que el estado
      // cambió en cada render -> "Maximum update depth exceeded".
      //
      // Correcto: seleccionar campos sueltos (referencias del store, estables) y
      // derivar el objeto con useMemo, o usar `useShallow` de zustand.
      'no-restricted-syntax': [
        'error',
        {
          selector:
            'CallExpression[callee.name=/^use[A-Za-z]*Store$/] > ArrowFunctionExpression > :matches(NewExpression, ObjectExpression, ArrayExpression)',
          message:
            'Este selector de zustand devuelve un valor nuevo en cada pasada (Object.is nunca lo ve igual) y provoca un bucle infinito de renders. Selecciona campos sueltos y deriva con useMemo, o usa useShallow.',
        },
        {
          // Solo salta si el .sort() cuelga DIRECTAMENTE del estado
          // (callee.object es un MemberExpression). `[...state.x].sort()` y
          // `state.x.slice().sort()` no casan, que es justo la forma correcta.
          selector:
            'CallExpression[callee.name=/^use[A-Za-z]*Store$/] > ArrowFunctionExpression CallExpression[callee.property.name=/^(sort|reverse)$/][callee.object.type="MemberExpression"]',
          message:
            'sort()/reverse() ordenan IN-PLACE: aquí estarías mutando el estado del store dentro de un selector, o sea durante el render y fuera de un set(). Copia antes: [...state.x].sort().',
        },
      ],

      // Vite: exportar cosas junto a componentes rompe el fast refresh.
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],

      // Ya lo reporta tsc (TS6133) con noUnusedLocals: no duplicar el aviso.
      '@typescript-eslint/no-unused-vars': 'off',
      'no-unused-vars': 'off',

      // `any` está muy extendido en el código actual; deuda a pagar aparte.
      '@typescript-eslint/no-explicit-any': 'warn',
    },
  },

  {
    files: ['**/*.{test,spec}.{ts,tsx}', 'src/test/**'],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
  },

  // Config de la raíz (tailwind, postcss): CommonJS sobre Node, no navegador.
  {
    files: ['*.config.js', '*.config.ts'],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
    rules: {
      '@typescript-eslint/no-require-imports': 'off',
    },
  }
);
