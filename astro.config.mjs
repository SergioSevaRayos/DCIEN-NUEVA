import { defineConfig } from 'astro/config';
import tailwind from '@astrojs/tailwind';

export default defineConfig({
  site: 'https://d-cien.es',
  integrations: [tailwind()],
  output: 'static', // Volvemos a estático para Hostinger tradicional
  vite: {
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