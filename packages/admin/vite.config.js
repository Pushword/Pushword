import { defineConfig } from 'vite'
import symfonyPlugin from 'vite-plugin-symfony'
import { resolve } from 'path'
import { viteStaticCopy } from 'vite-plugin-static-copy'

const filesToCopy = [
  {
    from: 'src/Resources/assets/logo.svg',
    to: '',
  },
]

const input = {
  admin: resolve(import.meta.dirname, 'src/Resources/assets/admin.js'),
}

export default defineConfig({
  plugins: [
    symfonyPlugin(),
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
        format: 'es',
      },
    },
    outDir: 'src/Resources/public',
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
  },
})
