import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: [
        'favicon.svg',
        'apple-touch-icon.png',
        'pwa-192x192.png',
        'pwa-512x512.png',
      ],
      manifest: {
        name: 'Community',
        short_name: 'Community',
        description: 'Member community directory and chat',
        theme_color: '#863bff',
        background_color: '#ffffff',
        display: 'standalone',
        start_url: '/',
        scope: '/',
        icons: [
          {
            src: 'pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png',
            purpose: 'any',
          },
          {
            src: 'pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'any',
          },
          {
            src: 'pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
      workbox: {
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//],
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2,webmanifest}'],
      },
    }),
  ],
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
          if (norm.includes('/node_modules/leaflet/')) {
            return 'vendor-leaflet'
          }
          if (norm.includes('/node_modules/leaflet-draw/')) {
            return 'vendor-leaflet-draw'
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
