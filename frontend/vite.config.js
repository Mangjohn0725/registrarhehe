import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
export default defineConfig(({ command }) => ({
  plugins: [react()],
  base: command === 'build' ? '/registrar/frontend/dist/' : '/',
  server: {
    port: 5173,
    proxy: { '/api': { target: 'http://localhost/registrar/backend/public', changeOrigin: true, secure: false } },
  },
}));
