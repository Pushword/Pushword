// modification must be sync with packages/skeleton/vite.config.js

import { defineConfig } from 'vite'
import symfonyPlugin from 'vite-plugin-symfony'
import { resolve } from 'path'
import tailwindcss from '@tailwindcss/vite'
import viteCopyPlugin from 'vite-plugin-static-copy'

const filesToCopy = [
  {
    from: 'src/Resources/assets/favicons/*',
    to: '',
  },
]

const input = {
  app: resolve(__dirname, '../js-helper/src/app.js'),
  style: resolve(__dirname, '../js-helper/src/app.css'),
}

export default defineConfig({
  plugins: [
    symfonyPlugin(),
    tailwindcss(),
    viteCopyPlugin.viteStaticCopy({
      targets: filesToCopy.map((copy) => ({
        src: copy.from,
        dest: copy.to || '',
      })),
    }),
  ],
  build: {
    rollupOptions: {
      input: input,
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name].[ext]',
      },
    },
    outDir: 'src/Resources/public',
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
  },
})
