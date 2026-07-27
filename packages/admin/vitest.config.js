import { defineConfig } from 'vitest/config'

// Scope to the JS tests only: the package also ships PHPUnit tests under tests/ and a
// vendor/ tree, neither of which Vitest should scan. The metadata detector walks bytes
// and touches no DOM, so the node environment is enough.
export default defineConfig({
  test: {
    include: ['tests/Js/**/*.test.js'],
  },
})
