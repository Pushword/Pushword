import { defineConfig } from 'vite'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

export default defineConfig({
  build: {
    lib: {
      entry: resolve(__dirname, 'src/Command/convert-json-to-markdown.ts'),
      formats: ['es'],
      fileName: () => 'convert-json-to-markdown.mjs',
      cssFileName: 'style',
    },
    outDir: './src/Command/convert-json-to-markdown-built',
    emptyOutDir: true,
    rollupOptions: {
      external: [],
      output: {
        banner: '#!/usr/bin/env node',
      },
    },
    minify: false,
    sourcemap: false,
    cssCodeSplit: false,
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src/assets'),
    },
  },
  // Ignorer les imports CSS pour ce build
  css: {
    modules: false,
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
    window: 'globalThis.window',
    document: 'globalThis.document',
  },
})
