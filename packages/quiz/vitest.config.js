import { defineConfig } from 'vitest/config'

// Scope to the JS runtime tests only: the package also ships PHPUnit tests under
// tests/ and a vendor/ tree, neither of which Vitest should scan.
export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['tests/**/*.test.js'],
  },
})
