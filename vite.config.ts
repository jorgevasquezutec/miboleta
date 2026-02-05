
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
        target: 'http://localhost',
        changeOrigin: true,
        secure: false,
      },
    },
  },
}));