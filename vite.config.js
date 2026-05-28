import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  build: {
    outDir: 'js',
    emptyOutDir: true,
    lib: {
      entry: resolve(__dirname, 'src/main.js'),
      name: 'Spesenerfassung',
      formats: ['iife'],
      fileName: () => 'spesenerfassung-main.js',
    },
    rollupOptions: {
      output: {
        inlineDynamicImports: true,
      },
    },
    sourcemap: false,
  },
  define: {
    __APP_ID__: JSON.stringify('spesenerfassung'),
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
})
