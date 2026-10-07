import { defineConfig } from 'vite'
import { resolve } from 'node:path'

// Mermaid's lazy diagram chunks are ES modules; the other core scripts are classic scripts.
export default defineConfig({
  base: './',
  build: {
    outDir: 'src/Resources/public/mermaid',
    emptyOutDir: true,
    // Optional layout engines (notably ELK) are large and already loaded on demand.
    chunkSizeWarningLimit: 1500,
    rolldownOptions: {
      preserveEntrySignatures: 'strict',
      input: {
        mermaid: resolve(import.meta.dirname, '../js-helper/src/mermaid.js'),
        front: resolve(import.meta.dirname, '../js-helper/src/mermaid-front.js'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name]-[hash].js',
        assetFileNames: '[name].[ext]',
      },
    },
  },
})
