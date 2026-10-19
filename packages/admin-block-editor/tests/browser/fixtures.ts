import { test as base, expect, type BrowserContext, type Page } from '@playwright/test'
import type { OutputBlockData } from '@editorjs/editorjs'
import { existsSync, readFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

type StorageState = Awaited<ReturnType<BrowserContext['storageState']>>
const directory = join(tmpdir(), 'com.github.pushword.pushword/tests', process.env.TEST_RUN_ID!)

async function waitForEditor(page: Page): Promise<void> {
  await page.waitForFunction(() => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')
    return holder && window.editors?.[holder.id] && !document.querySelector('[data-pw-baseline-pending]')
  })
}

export const test = base.extend<{ editorPage: Page }, { authState: StorageState }>({
  authState: [async ({ browser }, use, workerInfo) => {
    const statePath = join(directory, `auth-${workerInfo.project.name}.json`)
    // A failed test restarts its worker; reuse the login instead of hitting the login throttle.
    if (existsSync(statePath)) {
      await use(JSON.parse(readFileSync(statePath, 'utf8')))
      return
    }
    const context = await browser.newContext()
    const page = await context.newPage()
    await page.goto('http://127.0.0.1:9079/login')
    await page.locator('#inputEmail').fill('admin@example.tld')
    await page.locator('button[type=submit]').click()
    await page.locator('#inputPassword').fill('mySecr3tpAssword')
    await page.locator('button[type=submit]').click()
    await page.waitForURL('**/admin/**')
    const state = await context.storageState({ path: statePath })
    await context.close()
    await use(state)
  }, { scope: 'worker' }],
  storageState: async ({ authState }, use) => use(authState),
  editorPage: async ({ page, browser }, use, testInfo) => {
    const errors: string[] = []
    page.on('pageerror', error => errors.push(error.message))
    const pages: Record<string, string> = JSON.parse(readFileSync(join(directory, 'browser-pages.json'), 'utf8'))
    await page.goto(pages[testInfo.project.name]!)
    // TagsField moves focus to the title after initialization; wait before typing elsewhere.
    await expect(page.locator('[id$="_h1"]')).toBeFocused()
    await waitForEditor(page)
    testInfo.annotations.push({ type: 'browser version', description: `${testInfo.project.name} ${browser.version()}` })
    await use(page)
    expect(errors, 'Uncaught browser errors').toEqual([])
  },
})

export { expect }

export async function render(page: Page, blocks: OutputBlockData[]): Promise<void> {
  await page.evaluate(async blocks => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
    await window.editors[holder.id].blocks.render({ blocks })
  }, blocks)
  // Rendering replaces the history baseline; let its 500 ms mutation batch settle.
  await page.waitForTimeout(650)
}

export async function saveAndReload(page: Page): Promise<void> {
  const formUrl = page.url()
  const response = page.waitForResponse(response =>
    response.request().method() === 'POST' && response.url() === formUrl,
  )
  await Promise.all([
    page.waitForEvent('domcontentloaded'),
    page.locator('.action-saveAndContinue').click(),
  ])
  expect((await response).status(), 'The edit form must redirect after saving').toBe(302)
  await page.reload()
  await waitForEditor(page)
}
