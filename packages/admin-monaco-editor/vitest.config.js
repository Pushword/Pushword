import { defineConfig } from 'vitest/config'

// Only the markdown primitives are unit-tested: they are pure string maths, so the
// node environment is enough and monaco-editor never has to load.
export default defineConfig({
  test: {
    include: ['markdown/**/*.test.js'],
  },
})
