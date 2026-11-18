import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ command, mode }) => {
  const target = loadEnv(mode, '.', '').CLIPSHARE_API_TARGET || 'http://127.0.0.1:8000';

  const proxy = { target, changeOrigin: false };

  return {
    plugins: [react(), tailwindcss()],
    base: command === 'build' ? '/spa/' : '/',
    build: {
      outDir: '../public/spa',
      emptyOutDir: true,
    },
    server: {
      port: 5173,
      proxy: {
        '/api': proxy,
        '/uploads': proxy,
      },
    },
  };
});
