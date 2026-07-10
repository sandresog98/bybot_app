import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      // En desarrollo las llamadas relativas /api/v1/* se forwardean al backend
      '/api': {
        target: process.env.VITE_API_BASE_URL?.replace(/\/api\/v1$/, '') || 'http://localhost:3001',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    sourcemap: true,
  },
});