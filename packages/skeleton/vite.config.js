// @dev : modification must be sync with packages/core/vite.config.js

import { defineConfig } from 'vite'
import symfonyPlugin from 'vite-plugin-symfony'
import { resolve } from 'path'
import { existsSync } from 'fs'
import tailwindcss from '@tailwindcss/vite'
import viteCopyPlugin from 'vite-plugin-static-copy'
import { compression } from 'vite-plugin-compression2'

const filesToCopy = [
  {
    from: 'assets/favicons/*',
    to: '',
  },
]

const input = existsSync(resolve(__dirname, '../js-helper/src/app.js'))
  ? {
      app: resolve(__dirname, '../js-helper/src/app.js'),
      theme: resolve(__dirname, '../js-helper/src/app.css'),
    }
  : {
      app: 'node_modules/@pushword/js-helper/src/app.js',
      theme: 'node_modules/@pushword/js-helper/src/app.css',
    }

export default defineConfig({
  plugins: [
    compression({
      algorithms: ['zstd', 'gzip', 'brotliCompress'], // todo compare deflate and gzip usage with caddy
    }),
    symfonyPlugin(),
    tailwindcss(),
    viteCopyPlugin.viteStaticCopy({
      targets: filesToCopy.map((copy) => ({
        src: copy.from,
        dest: copy.to || '',
      })),
    }),
  ],
  //   server: {
  //     watch: {
  //       // 👇 specify files or directories to watch
  //       include: [
  //         'assets/**/*.{js,jsx,ts,tsx,vue,scss,css}',
  //         'templates/**/*.html.twig',
  //       ],
  //     },
  //   },
  build: {
    base: '/assets/',
    rollupOptions: {
      input: input,
    },
    outDir: 'public/assets',
    assetsDir: '.',
    emptyOutDir: true,
    manifest: true,
    sourcemap: true,
  },
  //   resolve: {
  //     alias: {
  //       '@': resolve(__dirname, 'assets'),
  //     },
  //   },
})
