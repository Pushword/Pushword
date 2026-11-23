import { resolve } from 'node:path'
import { defineConfig } from 'vite'

export default defineConfig({
  ssr: { noExternal: true },
  build: {
    ssr: resolve(import.meta.dirname, 'src/Resources/assets/admin.liveProjection.js'),
    outDir: '../core/src/Collaboration',
    emptyOutDir: false,
    minify: true,
    rolldownOptions: { output: { entryFileNames: 'live-editing.mjs' } },
  },
})
