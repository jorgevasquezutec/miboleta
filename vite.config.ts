
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react-swc';
import path from 'path';

export default defineConfig(({ mode }) => ({
  // Base path: always use root since we're serving from Laravel
  base: '/',
  plugins: [react()],
  resolve: {
    extensions: ['.js', '.jsx', '.ts', '.tsx', '.json'],
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@/core': path.resolve(__dirname, './src/core'),
      '@/domain': path.resolve(__dirname, './src/domain'),
      '@/infrastructure': path.resolve(__dirname, './src/infrastructure'),
      '@/presentation': path.resolve(__dirname, './src/presentation'),
      '@/shared': path.resolve(__dirname, './src/shared'),
    },
  },
  build: {
    target: 'esnext',
    outDir: 'dist',
    // Remove console.logs in production
    minify: 'esbuild',
    rollupOptions: {
      output: {
        // El worker de PDF.js (pdfjs-dist 5.x) solo existe upstream como
        // pdf.worker.min.mjs, y PDFViewer.tsx lo referencia con
        // `new URL(..., import.meta.url)`, asi que Vite lo emite tal cual con
        // extension .mjs. El problema: mime.types de nginx NO trae .mjs, asi
        // que se sirve como application/octet-stream y el navegador rechaza
        // tanto `new Worker(url, {type:'module'})` como el import() del fake
        // worker -> "Error al cargar el PDF" en TODOS los documentos.
        //
        // Se puede arreglar en cada nginx (y se hizo: bloque `location ~*
        // \.mjs$` en los conf del repo), pero eso no escala a las entregas
        // donde el cliente administra su propio servidor. Renombrando el
        // asset a .js, Rollup reescribe la URL en el bundle y el worker cae
        // en las reglas de estaticos que TODO servidor ya trae bien.
        // La extension no cambia nada del contenido: sigue siendo un modulo
        // ES y el navegador decide por el MIME, no por el nombre.
        assetFileNames: (info) => {
          const name = info.names?.[0] ?? info.name ?? '';
          return name.endsWith('.mjs')
            ? 'assets/[name]-[hash].js'
            : 'assets/[name]-[hash][extname]';
        },
        manualChunks: {
          // Vendor chunks - Core React ecosystem
          'vendor-react': ['react', 'react-dom', 'react-router-dom'],
          // UI Components - Radix primitives
          'vendor-ui': [
            '@radix-ui/react-dialog',
            '@radix-ui/react-dropdown-menu',
            '@radix-ui/react-select',
            '@radix-ui/react-popover',
            '@radix-ui/react-tabs',
            '@radix-ui/react-tooltip',
          ],
          // Heavy libraries - separated for better caching
          'vendor-charts': ['recharts'],
          'vendor-pdf': ['react-pdf'],
          'vendor-date': ['date-fns', 'react-day-picker'],
          'vendor-forms': ['react-hook-form'],
        },
      },
    },
  },
  esbuild: {
    // Remove console.log and console.debug in production
    drop: mode === 'production' ? ['console', 'debugger'] : [],
  },
  server: {
    port: 5173,
    open: false, // No abrir automáticamente porque accederemos desde localhost
    // Proxy API requests to Laravel backend
    proxy: {
      '/api': {
        target: `http://localhost:${process.env.MIBOLETA_HTTP_PORT || 8090}`,
        changeOrigin: true,
        secure: false,
      },
    },
  },
}));