import { defineConfig } from 'vitest/config';

export default defineConfig({
  publicDir: false,
  base: '/build/',
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    sourcemap: true,
    // MapLibre alone is about 1 MB minified, so the default 500 kB warning would always fire.
    chunkSizeWarningLimit: 1200,
    rolldownOptions: {
      input: { app: 'frontend/src/main.ts' },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
  test: {
    include: ['frontend/tests/**/*.test.ts'],
    environment: 'node',
  },
});
