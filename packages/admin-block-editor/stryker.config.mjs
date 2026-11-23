export default {
  testRunner: 'vitest',
  vitest: { configFile: 'vitest.config.mts' },
  mutate: ['src/assets/**/*.ts', '!src/assets/**/*.test.ts', '!src/assets/**/*.d.ts'],
  concurrency: 2,
  reporters: ['clear-text', 'progress', 'json'],
  clearTextReporter: { reportTests: false },
  jsonReporter: { fileName: process.env.STRYKER_REPORT_PATH ?? 'reports/mutation/mutation.json' },
}
