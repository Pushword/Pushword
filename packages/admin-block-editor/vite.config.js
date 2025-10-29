import { defineConfig } from 'vite'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'
import postcssNestedAncestors from 'postcss-nested-ancestors'
import postcssNested from 'postcss-nested'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

export default defineConfig({
  build: {
    sourcemap: true,
    rollupOptions: {
      input: './src/assets/admin-block-editor.ts',
      output: {
        entryFileNames: 'admin-block-editor.js',
        assetFileNames: 'style.css',
        format: 'es',
      },
    },
    outDir: './src/Resources/public',
    emptyOutDir: true,
    minify: 'terser',
    terserOptions: {
      format: {
        comments: false,
      },
    },
    cssCodeSplit: false, // Regroupe tout le CSS dans un seul fichier
  },
  css: {
    postcss: {
      plugins: [postcssNestedAncestors, postcssNested],
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src/assets'),
    },
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'development'),
  },
})
