import { defineConfig } from 'astro/config';
import tailwind from '@astrojs/tailwind';
import sitemap from '@astrojs/sitemap';
import partytown from '@astrojs/partytown';

export default defineConfig({
  site: 'https://d-cien.es',
  integrations: [
    tailwind(), 
    sitemap(),
    partytown({
      config: {
        forward: ['dataLayer.push'],
      },
    })
  ],
  output: 'static',
  vite: {
    // Proxy solo activo en dev: reenvía /api/* → dcien-backend.test
    // Así el navegador ve todo en localhost:4321 y las cookies de sesión funcionan
    server: {
      proxy: {
        '/api': {
          target: 'http://localhost:8080',
          changeOrigin: true,
          secure: false,
        },
        '/admin-descargas': {
          target: 'http://localhost:8080',
          changeOrigin: true,
          secure: false,
        },
      },
    },
    build: {
      cssMinify: 'esbuild',
      minify: 'terser',
      terserOptions: {
        compress: {
          drop_console: true,
          passes: 2,
        },
      },
    },
    ssr: {
      noExternal: ['sweetalert2'],
    },
  },
  compressHTML: true,
});