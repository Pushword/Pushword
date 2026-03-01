import { defineConfig } from 'vite'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'
import { cpSync, mkdirSync } from 'fs'
import postcssNestedAncestors from 'postcss-nested-ancestors'
import postcssNested from 'postcss-nested'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

function copyPrettierPlugin() {
  return {
    name: 'copy-prettier',
    closeBundle() {
      const outDir = resolve(__dirname, 'src/Resources/public/prettier')
      mkdirSync(outDir, { recursive: true })
      cpSync(resolve(__dirname, 'node_modules/prettier/standalone.js'), resolve(outDir, 'standalone.js'))
      cpSync(resolve(__dirname, 'node_modules/prettier/plugins/markdown.js'), resolve(outDir, 'markdown.js'))
    },
  }
}

export default defineConfig({
  build: {
    sourcemap: true,
    rollupOptions: {
      input: './src/assets/admin-block-editor.ts',
      external: ['prettier/standalone', 'prettier/plugins/markdown'],
      output: {
        entryFileNames: 'admin-block-editor.js',
        assetFileNames: 'style.css',
        format: 'iife',
        globals: {
          'prettier/standalone': 'prettierStandalone',
          'prettier/plugins/markdown': 'prettierMarkdown',
        },
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
    cssCodeSplit: false,
  },
  plugins: [copyPrettierPlugin()],
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
