import { test, expect, render, saveAndReload, editorUrl, waitForEditorWorker } from './fixtures'

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
  await render(page, [{ type: 'paragraph', data: { text: 'Content to preserve' } }])
  await saveAndReload(page)
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
  const saveUrl = page.url()
  const savePosts: string[] = []
  page.on('request', request => {
    if (request.method() === 'POST' && request.url() === saveUrl) savePosts.push(request.url())
  })
  await page.keyboard.press('ControlOrMeta+s')
  await expect(page.locator('[data-pw-editor-status]')).toBeVisible()
  expect(savePosts).toEqual([])
  await expect(source).toHaveValue(original)
  const response = page.waitForResponse(response => response.request().method() === 'POST' && response.url() === saveUrl)
  await page.evaluate(() => (window as any).releaseLoading())
  expect((await response).status()).toBe(204)
  await expect(page.locator('.action-saveAndReturn')).toBeEnabled()
  await page.reload()
  await expect(page.locator('[data-pw-editor-widget]')).toHaveAttribute('data-pw-editor-state', 'ready')
  await expect(page.locator('.ce-paragraph')).toHaveText('Content to preserve')
  await expect(page.locator('.h1Field')).toHaveValue('Title edited while loading')
  await waitForEditorWorker(page)
})

test('offers a reload after a failed import and preserves the original source', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Original source' } }])
  await saveAndReload(page)
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
  const saveUrl = page.url()
  const savePosts: string[] = []
  page.on('request', request => {
    if (request.method() === 'POST' && request.url() === saveUrl) savePosts.push(request.url())
  })
  await page.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(page.locator('[data-pw-editor-load-error]')).toBeVisible()
  expect(savePosts).toEqual([])
  await expect(page.locator('textarea[data-editorjs]')).toHaveValue(original)
  await page.getByRole('button', { name: 'Reload page', exact: true }).press('Enter')
  await expect(page.locator('[data-pw-editor-widget]')).toHaveAttribute('data-pw-editor-state', 'ready')
  await expect(page.locator('.ce-paragraph')).toHaveText('Original source')
  await waitForEditorWorker(page)
})
