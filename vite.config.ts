import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import laravel from 'laravel-vite-plugin'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.tsx'],
      refresh: true,
    }),
    react(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  server: {
    host: '0.0.0.0',
    port: Number(process.env.VITE_DEV_PORT ?? 5173),
    strictPort: true,
    cors: true,
    watch: {
      ignored: ['**/storage/framework/views/**'],
    },
  },
  build: {
    sourcemap: false,
    cssCodeSplit: true,
    chunkSizeWarningLimit: 900,
    rollupOptions: {
      output: {
        /** Keep heavyweight framework/editor/data-visualization dependencies in stable cacheable chunks. */
        manualChunks(id) {
          if (!id.includes('node_modules')) return undefined
          if (/[\/]node_modules[\/](react|react-dom|scheduler)[\/]/.test(id)) return 'vendor-react'
          if (id.includes('/node_modules/recharts/') || id.includes('\\node_modules\\recharts\\')) return 'vendor-charts'
          if (id.includes('/node_modules/@tiptap/') || id.includes('\\node_modules\\@tiptap\\') || id.includes('/node_modules/prosemirror-') || id.includes('\\node_modules\\prosemirror-')) return 'vendor-editor'
          if (id.includes('/node_modules/@dnd-kit/') || id.includes('\\node_modules\\@dnd-kit\\') || id.includes('/node_modules/gridstack/') || id.includes('\\node_modules\\gridstack\\')) return 'vendor-interaction'
          return 'vendor'
        },
      },
    },
  },
})
