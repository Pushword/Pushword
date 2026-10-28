import { defineConfig } from 'vite'

export default defineConfig({
  build: {
    license: { fileName: 'LICENSES.md' },
    lib: {
      entry: 'src/Resources/assets/calendar.js',
      formats: ['es'],
      fileName: () => 'calendar.js',
      cssFileName: 'calendar',
    },
    outDir: 'src/Resources/public/calendar',
    emptyOutDir: true,
  },
})
