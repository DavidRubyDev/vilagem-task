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
        // Language files are fetched at run time and change with their content, so they are hashed; the hand-written
        // HTML references only the fixed names of the other assets.
        assetFileNames: (asset) =>
          asset.originalFileNames.some((name) => name.startsWith('frontend/languages/'))
            ? 'languages/[name]-[hash][extname]'
            : '[name][extname]',
      },
    },
  },
  test: {
    include: ['frontend/tests/**/*.test.ts'],
    environment: 'node',
  },
});
