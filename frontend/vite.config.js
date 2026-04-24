import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
  server: {
    host: true,
    port: 9123,
    watch: {
      usePolling: true,
    },
  },
  build: {
    rollupOptions: {
      output: {
        // Rolldown (Vite 8+) expects a function; object form is Rollup-only.
        manualChunks(id) {
          const norm = id.replace(/\\/g, '/')
          if (
            norm.includes('/node_modules/vue/') ||
            norm.includes('/node_modules/vue-router/')
          ) {
            return 'vendor-vue'
          }
          if (
            norm.includes('/node_modules/leaflet/') ||
            norm.includes('/node_modules/leaflet-draw/')
          ) {
            return 'vendor-maps'
          }
          if (
            norm.includes('/node_modules/laravel-echo/') ||
            norm.includes('/node_modules/pusher-js/')
          ) {
            return 'vendor-realtime'
          }
        },
      },
    },
    chunkSizeWarningLimit: 600,
  },
  optimizeDeps: {
    include: ['leaflet', 'leaflet-draw'],
  },
  publicDir: 'public',
})
