import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import { viteStaticCopy } from 'vite-plugin-static-copy'
import { resolve } from 'path'

const filesToCopy = [
  {
    from: 'logo.svg',
    to: '',
  },
  {
    from: 'favicons/*',
    to: '',
  },
]

const input = {
  app: resolve(import.meta.dirname, 'app.js'),
  tw: resolve(import.meta.dirname, 'app.css'),
}

export default defineConfig({
  plugins: [
    tailwindcss(),
    viteStaticCopy({
      targets: filesToCopy.map((copy) => ({
        src: copy.from,
        dest: copy.to || '',
        rename: { stripBase: true },
      })),
    }),
  ],
  build: {
    rolldownOptions: {
      input: input,
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name].[ext]',
      },
    },
    outDir: '../../dev-app/public/assets',
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
  },
})
