// modification must be sync with packages/dev-app/vite.config.js

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
  alpine: resolve(__dirname, '../js-helper/src/alpine.js'),
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
  resolve: {
    modules: [resolve(__dirname, '../js-helper/node_modules'), 'node_modules'],
  },
  build: {
    rollupOptions: {
      input: input,
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name].[ext]',
        // Give every chunk its own scope. They are served as classic scripts, so
        // their top-level declarations otherwise share one global lexical scope:
        // the minifier named a const in app.js and a function in alpine.js `_e`
        // alike, and whichever parsed second threw on the duplicate — leaving
        // Alpine dead on every page. Rollup refuses iife for a multi-entry
        // build, so the wrap is done here.
        banner: '(function(){',
        footer: '})();',
      },
    },
    outDir: 'src/Resources/public',
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
  },
})
