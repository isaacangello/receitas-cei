import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import { readdirSync, statSync } from 'fs'
import { join, resolve } from 'path'

function directorySlashPlugin() {
  return {
    name: 'directory-slash',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        const urlPath = decodeURIComponent(req.url.split('?')[0])
        if (urlPath.startsWith('/api') || urlPath.startsWith('/@') || urlPath.includes('.')) {
          return next()
        }
        if (urlPath.endsWith('/')) {
          return next()
        }

        const root = resolve(__dirname, 'public_html')
        const filePath = join(root, urlPath)

        try {
          if (statSync(filePath).isDirectory()) {
            res.writeHead(301, { Location: urlPath + '/' })
            res.end()
            return
          }
        } catch {}

        next()
      })
    },
  }
}

export default defineConfig({
  root: 'public_html',
  plugins: [tailwindcss(), directorySlashPlugin()],
  build: {
    outDir: '.',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        main: 'index.html',
        admin: 'admin/index.html',
      },
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
})
