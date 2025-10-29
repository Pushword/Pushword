import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import viteCopyPlugin from 'vite-plugin-static-copy'
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
  app: resolve(__dirname, 'app.js'),
  tw: resolve(__dirname, 'app.css'),
}

export default defineConfig({
  plugins: [
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
    outDir: '../../skeleton/public/assets',
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
  },
})
