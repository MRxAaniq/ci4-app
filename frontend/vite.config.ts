/// <reference types="node" />

import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_');
  const apiTarget = env.VITE_API_TARGET || 'http://localhost:8080';

  return {
    plugins: [vue()],
    server: {
      port: 5173,
      proxy: {
        // Proxy API to backend so cookies work without CORS pain.
        '/api': {
          target: apiTarget,
          changeOrigin: true,
          secure: false,
        },
      },
    },
  };
});
