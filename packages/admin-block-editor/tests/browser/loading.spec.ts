import type { Page } from '@playwright/test'
import { test as base, expect, render, saveAndReload, editorUrl } from './fixtures'

type WorkerStartupWindow = typeof window & { pwTestEditorWorkers: { started: number, ready: number } }

const test = base.extend({
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
})

async function waitForEditorWorker(page: Page): Promise<void> {
  // Page network-idle does not include the module imports inside a worker.
  await expect.poll(() => page.evaluate(() => {
    const { started, ready } = (window as WorkerStartupWindow).pwTestEditorWorkers
    return started > 0 && ready === started
  }), { message: 'Monaco worker imports must finish before navigating' }).toBe(true)
}

test.describe('before initialization', () => {
  test.use({ javaScriptEnabled: false })

  test('renders loading feedback before JavaScript runs', async ({ page }, testInfo) => {
    await page.goto(editorUrl(testInfo))
    await expect(page.locator('[data-pw-editor-widget]')).toHaveAttribute('data-pw-editor-state', 'loading')
    await expect(page.getByText('Loading content…', { exact: true })).toBeVisible()
    await expect(page.locator('.editorjs-holder')).toHaveAttribute('inert', '')
    await expect(page.locator('.editorjs-holder')).toHaveAttribute('aria-busy', 'true')
    await expect(page.locator('.pw-editor-mode:disabled')).toHaveCount(3)
    await expect(page.locator('.h1Field')).toBeEditable()
    await page.screenshot({ animations: 'disabled', path: testInfo.outputPath('loading-desktop.png') })
    await page.setViewportSize({ width: 390, height: 844 })
    await expect(page.getByText('Loading content…', { exact: true })).toBeVisible()
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
    await page.screenshot({ animations: 'disabled', path: testInfo.outputPath('loading-mobile.png') })
    await page.emulateMedia({ reducedMotion: 'reduce' })
    expect(await page.locator('.pw-editor-skeleton').evaluate(element => getComputedStyle(element).animationName)).toBe('none')
  })
})

test('waits for Markdown content before revealing or saving the editor', async ({ editorPage: page }) => {
  await waitForEditorWorker(page)
  await render(page, [{ type: 'paragraph', data: { text: 'Content to preserve' } }])
  await saveAndReload(page)
  await waitForEditorWorker(page)
  await page.addInitScript(() => {
    if (sessionStorage.getItem('pw-test-loading-delayed')) return
    sessionStorage.setItem('pw-test-loading-delayed', '1')
    Object.defineProperty(window, 'EditorJsParseMarkdown', {
      configurable: true,
      set(Parser) {
        Object.defineProperty(window, 'EditorJsParseMarkdown', { value: class extends Parser {
          async parseMarkdown() {
            await new Promise<void>(resolve => { (window as any).releaseLoading = resolve })
            return super.parseMarkdown()
          }
        } })
      },
    })
  })
  await page.reload()
  await page.waitForFunction(() => typeof (window as any).releaseLoading === 'function')
  await waitForEditorWorker(page)
  await expect(page.locator('[data-pw-editor-status]')).toBeVisible()
  await expect(page.locator('.ce-paragraph')).toBeHidden()
  await expect(page.locator('.h1Field')).toBeFocused()
  await page.locator('.h1Field').fill('Title edited while loading')
  await expect(page.locator('.h1Field')).toHaveValue('Title edited while loading')
  const source = page.locator('textarea[data-editorjs]')
  const original = await source.inputValue()
  const posts: string[] = []
  page.on('request', request => { if (request.method() === 'POST') posts.push(request.url()) })
  await page.locator('.action-saveAndContinue').click()
  await expect(page.locator('[data-pw-editor-status]')).toBeVisible()
  expect(posts).toEqual([])
  await expect(source).toHaveValue(original)
  const response = page.waitForResponse(response => response.request().method() === 'POST' && response.url() === page.url())
  await page.evaluate(() => (window as any).releaseLoading())
  expect((await response).status()).toBe(302)
  await expect(page.locator('[data-pw-editor-widget]')).toHaveAttribute('data-pw-editor-state', 'ready')
  await expect(page.locator('.ce-paragraph')).toHaveText('Content to preserve')
  await expect(page.locator('.h1Field')).toHaveValue('Title edited while loading')
  await waitForEditorWorker(page)
})

test('offers a reload after a failed import and preserves the original source', async ({ editorPage: page }) => {
  await waitForEditorWorker(page)
  await render(page, [{ type: 'paragraph', data: { text: 'Original source' } }])
  await saveAndReload(page)
  await waitForEditorWorker(page)
  const original = await page.locator('textarea[data-editorjs]').inputValue()
  await page.addInitScript(() => {
    if (sessionStorage.getItem('pw-test-loading-failed')) return
    sessionStorage.setItem('pw-test-loading-failed', '1')
    Object.defineProperty(window, 'EditorJsParseMarkdown', {
      configurable: true,
      set(Parser) {
        Object.defineProperty(window, 'EditorJsParseMarkdown', { value: class extends Parser {
          async parseMarkdown() { throw new Error('Test import failure') }
        } })
      },
    })
  })
  await page.reload()
  await expect(page.locator('[data-pw-editor-widget]')).toHaveAttribute('data-pw-editor-state', 'failed')
  await expect(page.locator('[data-pw-editor-loading]')).toBeHidden()
  await expect(page.locator('[data-pw-editor-load-error]')).toBeVisible()
  await expect(page.locator('.pw-editor-mode:disabled')).toHaveCount(3)
  await expect(page.locator('textarea[data-editorjs]')).toHaveValue(original)
  await waitForEditorWorker(page)
  const posts: string[] = []
  page.on('request', request => { if (request.method() === 'POST') posts.push(request.url()) })
  await page.locator('.action-saveAndContinue').click()
  await expect(page.locator('[data-pw-editor-load-error]')).toBeVisible()
  expect(posts).toEqual([])
  await expect(page.locator('textarea[data-editorjs]')).toHaveValue(original)
  await page.getByRole('button', { name: 'Reload page', exact: true }).press('Enter')
  await expect(page.locator('[data-pw-editor-widget]')).toHaveAttribute('data-pw-editor-state', 'ready')
  await expect(page.locator('.ce-paragraph')).toHaveText('Original source')
  await waitForEditorWorker(page)
})
