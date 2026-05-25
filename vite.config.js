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
    rollupOptions: {
      input: resolve(__dirname, 'src/main.js'),
      output: {
        entryFileNames: 'spesenerfassung-[name].js',
        chunkFileNames: 'spesenerfassung-[name].js',
        assetFileNames: 'spesenerfassung-[name].[ext]',
      },
    },
    sourcemap: false,
  },
  define: {
    __APP_ID__: JSON.stringify('spesenerfassung'),
  },
})
