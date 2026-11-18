import { test as base, expect, type BrowserContext, type Page, type TestInfo } from '@playwright/test'
import type { OutputBlockData } from '@editorjs/editorjs'
import { existsSync, readFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

type StorageState = Awaited<ReturnType<BrowserContext['storageState']>>
const directory = join(tmpdir(), 'com.github.pushword.pushword/tests', process.env.TEST_RUN_ID!)

type WorkerStartupWindow = typeof window & { pwTestEditorWorkers: { started: number, ready: number } }

export async function waitForEditorWorker(page: Page): Promise<void> {
  // Page network-idle does not include the module imports inside a worker.
  await expect.poll(() => page.evaluate(() => {
    const { started, ready } = (window as WorkerStartupWindow).pwTestEditorWorkers
    return started > 0 && ready === started
  }), { message: 'Monaco worker imports must finish before navigating' }).toBe(true)
}

async function waitForEditor(page: Page): Promise<void> {
  await page.waitForFunction(() => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')
    return holder && window.editors?.[holder.id]?.blocks && !document.querySelector('[data-pw-baseline-pending]')
  })
  await waitForEditorWorker(page)
}

export function editorUrl(testInfo: TestInfo): string {
  const pages: Record<string, string> = JSON.parse(readFileSync(join(directory, 'browser-pages.json'), 'utf8'))
  return pages[`${testInfo.project.name}:${testInfo.parallelIndex}`]!
}

export const test = base.extend<{ editorPage: Page }, { authState: StorageState }>({
  page: async ({ page }, use) => {
    await page.addInitScript(() => {
      const state = window as WorkerStartupWindow
      state.pwTestEditorWorkers = { started: 0, ready: 0 }
      window.Worker = new Proxy(window.Worker, {
        construct(Target, args) {
          const worker = Reflect.construct(Target, args) as Worker
          if ((args[1] as WorkerOptions | undefined)?.name === 'editorWorkerService') {
            state.pwTestEditorWorkers.started++
            worker.addEventListener('message', event => {
              // Monaco sends this after its module and static imports have loaded.
              if (event.data?.type === 'vscode-worker-ready') state.pwTestEditorWorkers.ready++
            })
          }
          return worker
        },
      })
    })
    await use(page)
  },
  authState: [async ({ browser }, use, workerInfo) => {
    const statePath = join(directory, `auth-${workerInfo.parallelIndex}.json`)
    // Sessions work across browsers; reuse each slot's login across projects and worker restarts.
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
    page.on('console', message => {
      if (message.type() === 'error') errors.push(message.text())
    })
    const url = editorUrl(testInfo)
    // Each parallel worker owns a page and retains its lock between journeys and restarts.
    await page.addInitScript(tabId => sessionStorage.setItem('pw-edit-lock-tab-id', tabId),
      `${process.env.TEST_RUN_ID}:${testInfo.project.name}:${testInfo.parallelIndex}`)
    const lockResponse = page.waitForResponse(response => response.url().endsWith('/lock/ping'))
    await page.goto(url)
    const lockInfo = await (await lockResponse).json()
    expect(lockInfo.isOwner, 'The journey must own the page edit lock').toBe(true)
    await expect(page.locator('#pw-edit-lock-banner')).toBeHidden()
    // TagsField moves focus to the title after initialization; wait before typing elsewhere.
    await expect(page.locator('[id$="_h1"]')).toBeFocused()
    await waitForEditor(page)
    testInfo.annotations.push({ type: 'browser version', description: `${testInfo.project.name} ${browser.version()}` })
    testInfo.annotations.push({ type: 'platform', description: await page.evaluate(() => navigator.userAgent) })
    testInfo.annotations.push({ type: 'worker page', description: url })
    await use(page)
    expect(errors, 'Browser exceptions and console errors').toEqual([])
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

export async function expectInsertionPreservesLayout(page: Page): Promise<void> {
  const measure = () => page.locator('.codex-editor__redactor').evaluate(redactor =>
    [redactor, ...redactor.querySelectorAll('.ce-block, [contenteditable="true"], .editorjs-monaco-wrapper')]
      .map(element => element.getBoundingClientRect().toJSON()),
  )
  const withInsertion = await measure()
  await page.locator('.pw-block-insertion').evaluateAll(elements => {
    for (const element of elements) (element as HTMLElement).style.display = 'none'
  })
  expect(await measure(), 'Insertion controls must not change the document layout').toEqual(withInsertion)
  await page.locator('.pw-block-insertion').evaluateAll(elements => {
    for (const element of elements) (element as HTMLElement).style.removeProperty('display')
  })
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
  // The redirect loads persisted content; another reload can abort editor workers.
  await waitForEditor(page)
}
