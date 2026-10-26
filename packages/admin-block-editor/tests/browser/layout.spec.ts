import { test, expect, render } from './fixtures'

test('writing layout stays readable across viewports and the outline releases mobile content', async ({
  editorPage: page,
}) => {
  await render(page, [{ type: 'paragraph', data: { text: 'A paragraph to edit.' } }])
  await page.evaluate(() => localStorage.setItem('pw-outline-collapsed', '0'))
  await page.setViewportSize({ width: 1440, height: 1000 })
  if (await page.locator('.pw-outline').isHidden())
    await page.locator('.pw-outline-opener').click()
  await expect(page.locator('.pw-outline-toggle')).toHaveAccessibleName(
    'Close the outline',
  )
  await expect
    .poll(() =>
      page.locator('.ce-paragraph').evaluate((el) => el.getBoundingClientRect().width),
    )
    .toBeLessThanOrEqual(576)
  await expect(page.locator('[data-pw-editor-mode="json"]')).toContainText('JSON')
  await expect(page.locator('[data-pw-editor-mode="markdown"]')).toContainText('Markdown')

  for (const width of [1024, 375, 320]) {
    await page.setViewportSize({ width, height: 900 })
    await expect(page.locator('.pw-outline')).toBeHidden()
    await expect
      .poll(() => page.evaluate(() => document.documentElement.scrollWidth))
      .toBeLessThanOrEqual(width)
    await expect(page.locator('[data-pw-editor-mode="markdown"]')).toBeInViewport()
    await expect(page.locator('.action-saveAndReturn')).toBeInViewport()
  }
  await page.locator('.pw-outline-opener').click()
  await expect(page.locator('.pw-outline-toggle')).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(page.locator('.pw-outline')).toBeHidden()
  await expect(page.locator('.pw-outline-opener')).toBeFocused()
  await page.locator('.pw-outline-opener').click()
  await page.locator('.pw-outline-label').first().click()
  await expect(page.locator('.pw-outline')).toBeHidden()
})

test('source mode state and clipboard success have visible and accessible feedback', async ({
  editorPage: page,
}) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Copy this paragraph.' } }])
  const markdown = page.locator('[data-pw-editor-mode="markdown"]')
  await expect(markdown).toHaveAttribute('aria-pressed', 'false')
  await markdown.click()
  await expect(markdown).toHaveAttribute('aria-pressed', 'true')
  await expect(page.locator('.editorjs-holder')).toBeHidden()
  await markdown.click()
  await expect(markdown).toHaveAttribute('aria-pressed', 'false')
  await expect(page.locator('.ce-paragraph')).toHaveText('Copy this paragraph.')
  await page.locator('[data-pw-copy-label]').click()
  await expect(page.locator('[data-pw-copy-label]')).toHaveText('Copied')
  await expect(page.locator('[data-pw-copy-status]')).toHaveText(
    'Markdown copied to the clipboard.',
  )
  await page.evaluate(() =>
    document.body.classList.replace('ea-light-scheme', 'ea-dark-scheme'),
  )
  await markdown.click()
  await expect(page.locator('.pw-md .monaco-editor')).toHaveClass(/vs-dark/)
  await page.evaluate(() =>
    document.body.classList.replace('ea-dark-scheme', 'ea-light-scheme'),
  )
  await expect(page.locator('.pw-md .monaco-editor')).not.toHaveClass(/vs-dark/)
  await markdown.click()
})

test('editor and settings use readable theme colors and bounded diagnostics', async ({
  editorPage: page,
}) => {
  await render(page, [
    {
      type: 'quote',
      data: { text: 'A quoted paragraph.', caption: 'An author', alignment: 'left' },
    },
  ])
  // The report is offered quietly before any failure, then on an alert surface.
  for (const failed of [false, true]) {
    if (failed)
      await page
        .locator('.editorjs-holder')
        .evaluate((holder) =>
          holder.dispatchEvent(
            new CustomEvent('pw:editor-error', { detail: { operation: 'copy' } }),
          ),
        )
    for (const theme of ['dark', 'light']) {
      await page.evaluate((theme) => {
        document.body.classList.toggle('ea-dark-scheme', theme === 'dark')
        document.body.classList.toggle('ea-light-scheme', theme === 'light')
      }, theme)
      await expect
        .poll(() =>
          page.evaluate(() => {
            const color = (selector: string, property: string) =>
              getComputedStyle(document.querySelector(selector)!).getPropertyValue(property)
            return {
              titleMatches: color('.h1Field', 'color') === color('body', 'color'),
              outlineMatches:
                color('.pw-outline', 'background-color') ===
                color('body', 'background-color'),
              panelMatches:
                color('.pw-settings-toggle', 'color') === color('body', 'color'),
              captionVisible:
                color('.cdx-quote__caption', 'color') !== color('body', 'background-color'),
            }
          }),
        )
        .toEqual({
          titleMatches: true,
          outlineMatches: true,
          panelMatches: true,
          captionVisible: true,
        })
      await expect
        .poll(async () => {
          const contrasts = await page.evaluate((failed) => {
            const canvas = document.createElement('canvas')
            canvas.width = canvas.height = 1
            const context = canvas.getContext('2d')!
            const luminance = (color: string): number => {
              context.fillStyle = color
              context.fillRect(0, 0, 1, 1)
              const rgb = [...context.getImageData(0, 0, 1, 1).data]
                .slice(0, 3)
                .map((value) => {
                  const component = value / 255
                  return component <= 0.04045
                    ? component / 12.92
                    : ((component + 0.055) / 1.055) ** 2.4
                })
              return rgb[0]! * 0.2126 + rgb[1]! * 0.7152 + rgb[2]! * 0.0722
            }
            return [
              '.pw-editor-mode',
              '.pw-outline-title',
              '.cdx-quote__caption',
              '.pw-editor-diagnostics summary',
              '.pw-editor-diagnostics .text-muted',
              '.pw-editor-suggestions',
              ...(failed ? ['[data-pw-editor-error]'] : []),
            ].map((selector) => {
              const element = document.querySelector(selector)!
              let surface = element
              while (
                getComputedStyle(surface).backgroundColor === 'rgba(0, 0, 0, 0)' &&
                surface.parentElement
              )
                surface = surface.parentElement
              const foreground = luminance(getComputedStyle(element).color)
              const background = luminance(getComputedStyle(surface).backgroundColor)
              return (
                (Math.max(foreground, background) + 0.05) /
                (Math.min(foreground, background) + 0.05)
              )
            })
          }, failed)
          return Math.min(...contrasts)
        })
        .toBeGreaterThanOrEqual(4.5)
    }
  }
  const panel = page.locator('[data-pw-diagnostics-for]')
  await expect(panel).toBeVisible()
  await panel.locator('summary').click()
  await page.setViewportSize({ width: 320, height: 900 })
  await panel.scrollIntoViewIfNeeded()
  await expect
    .poll(() => page.evaluate(() => document.documentElement.scrollWidth))
    .toBeLessThanOrEqual(320)
  await expect(page.locator('[data-pw-download-diagnostic]')).toBeInViewport()
})

test.describe('touch controls', () => {
  test.use({ hasTouch: true })

  test('editor, settings and outline controls have 44 pixel touch targets', async ({
    editorPage: page,
  }) => {
    await page.setViewportSize({ width: 375, height: 900 })
    for (const selector of [
      '.pw-editor-mode',
      '.pw-editor-diagnostics summary',
      '.pw-settings-toggle',
      '.page-actions .btn',
    ]) {
      const heights = await page
        .locator(selector)
        .evaluateAll((elements) =>
          elements.map((el) => el.getBoundingClientRect().height),
        )
      for (const height of heights) expect(height).toBeGreaterThanOrEqual(44)
    }
    await page.locator('.pw-outline-opener').click()
    const size = await page
      .locator('.pw-outline-toggle')
      .evaluate((el) => ({
        width: el.getBoundingClientRect().width,
        height: el.getBoundingClientRect().height,
      }))
    expect(size.width).toBeGreaterThanOrEqual(44)
    expect(size.height).toBeGreaterThanOrEqual(44)
  })
})
