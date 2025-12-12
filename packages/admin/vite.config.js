import { defineConfig } from 'vite'
import symfonyPlugin from 'vite-plugin-symfony'
import { resolve } from 'path'
import viteCopyPlugin from 'vite-plugin-static-copy'

const filesToCopy = [
  {
    from: 'src/Resources/assets/logo.svg',
    to: '',
  },
]

const input = {
  admin: resolve(__dirname, 'src/Resources/assets/admin.js'),
}

export default defineConfig({
  plugins: [
    symfonyPlugin(),
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
        format: 'iife',
      },
    },
    outDir: 'src/Resources/public',
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
  },
})
