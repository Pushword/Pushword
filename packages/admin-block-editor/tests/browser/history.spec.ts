import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { test, expect, render } from './fixtures'

interface BrowserReport {
  done: boolean
  results: Array<{ name: string; passed: boolean; error?: string; detail?: unknown }>
}

for (const [file, reportName, count] of [
  ['undo.js', 'undoBrowserChecks', 59],
  ['undo-selection.js', 'undoSelectionChecks', 16],
  ['undo-inline.js', 'undoInlineChecks', 17],
  ['performance.js', 'editorPerformanceChecks', 10],
] as const) {
  test(`configured editor: ${file}`, async ({ editorPage: page }, testInfo) => {
    await render(page, [{ type: 'paragraph', data: { text: 'Baseline' } }])
    await page.evaluate(readFileSync(join(__dirname, file), 'utf8'))
    await page.waitForFunction(name =>
      (window as unknown as Record<string, BrowserReport>)[name]?.done,
    reportName, { timeout: 100_000 })
    const report = await page.evaluate(name =>
      (window as unknown as Record<string, BrowserReport>)[name]!, reportName)
    await testInfo.attach(reportName, { body: JSON.stringify(report, null, 2), contentType: 'application/json' })
    expect(report.results).toHaveLength(count)
    for (const result of report.results) {
      await test.step(result.name, async () => {
        expect.soft(result.passed, result.error ?? JSON.stringify(result.detail) ?? result.name).toBe(true)
      })
    }
  })
}
