import { defineConfig } from 'vitest/config'

// Markdown primitives and the bundled sanitizer can be checked without loading Monaco.
export default defineConfig({
  test: {
    include: ['markdown/**/*.test.js', 'build.test.js'],
  },
})
