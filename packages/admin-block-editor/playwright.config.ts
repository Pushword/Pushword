import { defineConfig, devices } from '@playwright/test'
import { randomUUID } from 'node:crypto'

// Inherited by the web server and test workers; every run owns its database and files.
process.env.TEST_RUN_ID ??= `editor-${randomUUID()}`
const windows = Boolean(process.env.PW_EDITOR_WINDOWS)
const resultsDirectory = windows ? 'test-results-windows' : 'test-results'

export default defineConfig({
  testDir: './tests/browser',
  testMatch: '**/*.spec.ts',
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: 0,
  timeout: 120_000,
  expect: { timeout: 10_000 },
  outputDir: `./${resultsDirectory}`,
  metadata: process.env.PW_EDITOR_WINDOWS_VERSION ? { windows: process.env.PW_EDITOR_WINDOWS_VERSION } : {},
  reporter: [
    ['line'],
    ['html', { open: 'never', outputFolder: windows ? 'playwright-report-windows' : 'playwright-report' }],
    ['json', { outputFile: `${resultsDirectory}/results.json` }],
  ],
  use: {
    baseURL: 'http://127.0.0.1:9079',
    actionTimeout: 10_000,
    navigationTimeout: 30_000,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chrome', use: { ...devices['Desktop Chrome'], channel: 'chrome' } },
    { name: 'msedge', use: {
      ...devices['Desktop Edge'], channel: 'msedge',
      ...(process.env.PW_EDITOR_EDGE_WS_ENDPOINT ? {
        connectOptions: { wsEndpoint: process.env.PW_EDITOR_EDGE_WS_ENDPOINT },
        locale: 'fr-FR',
      } : {}),
    } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
  ],
  webServer: {
    command: 'php tests/browser/server.php',
    env: { APP_ENV: 'test', APP_DEBUG: '1' },
    url: 'http://127.0.0.1:9079/login',
    reuseExistingServer: false,
    timeout: 120_000,
    gracefulShutdown: { signal: 'SIGTERM', timeout: 5000 },
  },
})
