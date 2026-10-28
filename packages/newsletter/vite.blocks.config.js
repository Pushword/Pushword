import { defineConfig } from 'vite'

export default defineConfig({
  build: {
    lib: {
      entry: 'src/Resources/assets/email-block.js',
      name: 'PushwordNewsletterBlocks',
      formats: ['iife'],
      fileName: () => 'blocks.js',
    },
    outDir: 'src/Resources/public/blocks',
    emptyOutDir: true,
  },
})
