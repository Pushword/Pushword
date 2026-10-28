import { test, expect, render } from './fixtures'
import type { Page } from '@playwright/test'

async function openCampaign(page: Page): Promise<void> {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('/admin/newsletter/campaign/new')
  await page.locator('#Campaign_audience').selectOption({ label: 'Localhost news (localhost.dev)' })
  await page.waitForFunction(() => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')
    return holder && window.editors?.[holder.id]?.blocks
  })
  await expect(page.locator('.pw-newsletter-design-canvas')).toBeVisible()
}

for (const kind of ['campaign', 'automation'] as const) {
  test(`${kind} preview loads an image from the media library`, async ({
    page,
  }, testInfo) => {
    // The fixture's canonical domain is not served by the local test server.
    await page.route('https://localhost.dev/media/**', async (route) => {
      const response = await route.fetch({
        url: route
          .request()
          .url()
          .replace('https://localhost.dev', 'http://127.0.0.1:9079'),
      })
      await route.fulfill({ response })
    })
    if (kind === 'campaign') {
      await openCampaign(page)
    } else {
      await page.goto('/admin/newsletter/automation/new')
      await page
        .locator('#Automation_audience')
        .selectOption({ label: 'Localhost news (localhost.dev)' })
      await expect(
        page.locator('textarea[data-pw-criteria]:not(.pw-criteria-hidden)'),
      ).toHaveCount(0)
      await page.locator('.field-collection-add-button').first().click()
      await page.waitForFunction(() => {
        const holder = document.querySelector<HTMLElement>('.editorjs-holder')
        return holder && window.editors?.[holder.id]?.blocks
      })
      await page.locator('.pw-newsletter-step-preview summary').first().click()
    }
    await render(page, [
      { type: 'image', data: { media: '1.jpg', caption: 'Initial caption' } },
    ])
    await page.locator('.image-tool__caption').fill('Mountain & lake')
    await expect(page.locator('textarea[id$="_bodyMarkdown"]')).toHaveValue(
      '![Mountain & lake](1.jpg)',
    )
    await page.locator('[id$="_subject"]').fill('Trip newsletter')
    const preview = page.locator(`[data-preview-kind="${kind}"]`)
    await expect(preview.locator('iframe')).toHaveAttribute(
      'srcdoc',
      /src="https:\/\/localhost\.dev\/media\/default\/1\.jpg"/,
    )
    await expect(preview.locator('[data-preview-status]')).toHaveAttribute('data-state', 'ready')
    const image = preview.frameLocator('iframe').locator('img[alt="Mountain & lake"]')
    await expect
      .poll(() => image.evaluate((img) => (img as HTMLImageElement).naturalWidth))
      .toBeGreaterThan(0)
    for (const width of ['mobile', 'desktop']) {
      await preview.locator(`[data-preview-width="${width}"]`).click()
      await expect(image).toBeVisible()
    }
    await preview.screenshot({ path: testInfo.outputPath(`${kind}-image.png`) })
  })
}

test('automation steps keep their editors independent when added, saved and reopened', async ({ page }) => {
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('/admin/newsletter/automation/new')
  await page.locator('#Automation_name').fill('Editor sequence')
  await page.locator('#Automation_audience').selectOption({ label: 'Localhost news (localhost.dev)' })
  await expect(page.locator('textarea[data-pw-criteria]:not(.pw-criteria-hidden)')).toHaveCount(0)
  await page.locator('.field-collection-add-button').first().click()
  await page.locator('.field-collection-add-button').first().click()
  const entries = page.locator('.pw-newsletter-sequence .field-collection-item')
  await expect(entries).toHaveCount(2)
  await expect(entries.nth(0).locator('.accordion-button').first()).toContainText('Email 1')
  await expect(entries.nth(1).locator('.accordion-button').first()).toContainText('Email 2')
  for (const [index, text] of ['First message', 'Second message'].entries()) {
    await entries.nth(index).locator('[id$="_subject"]').fill(text)
    await entries.nth(index).locator('.ce-paragraph').fill(text)
    await expect(entries.nth(index).locator('textarea[id$="_bodyMarkdown"]')).toHaveValue(text)
  }
  const first = entries.nth(0)
  const second = entries.nth(1)
  await first.locator('.ce-paragraph').focus()
  await page.keyboard.press('Control+u')
  await expect(first.locator('.editorjs-holder')).toBeHidden()
  await expect(second.locator('.editorjs-holder')).toBeVisible()
  await expect(first.locator('.monaco-editor')).toBeVisible()
  await first.locator('[data-pw-editor-mode="markdown"]').click()
  await expect(first.locator('.editorjs-holder')).toBeVisible()
  await second.locator('[data-pw-editor-mode="json"]').click()
  await expect(second.locator('.monaco-editor')).toBeVisible()
  await expect(first.locator('.editorjs-holder')).toBeVisible()
  await second.locator('[data-pw-editor-mode="json"]').click()
  await expect(second.locator('.editorjs-holder')).toBeVisible()
  await page.evaluate(() => Object.defineProperty(navigator.clipboard, 'writeText', {
    value: async (text: string) => { document.body.dataset.copiedMarkdown = text },
  }))
  await second.locator('[data-pw-copy-markdown]').click()
  await expect(page.locator('body')).toHaveAttribute('data-copied-markdown', 'Second message')
  await page.getByRole('button', { name: 'Create automation', exact: true }).click()
  await page.waitForURL('**/admin/newsletter/automation')
  const row = page.locator('tr').filter({ hasText: 'Editor sequence' })
  await row.getByRole('button', { name: 'Actions for row 1' }).click()
  await row.locator('.action-edit').click()
  await expect(entries).toHaveCount(2)
  await expect(first.locator('.ce-paragraph')).toHaveText('First message')
  await expect(second.locator('.ce-paragraph')).toHaveText('Second message')
  await second.locator('[data-pw-editor-mode="markdown"]').click()
  await expect(second.locator('.monaco-editor')).toBeVisible()
  await second.locator('.field-collection-delete-button').click()
  await expect(entries).toHaveCount(1)
  await expect.poll(() => page.evaluate(() => Object.keys(window.editors).length)).toBe(1)
  await expect.poll(() => page.evaluate(() => window.monaco!.editor.getModels().length)).toBe(0)
  expect(errors).toEqual([])
})

test('campaign link options remain clickable over the live preview', async ({ page }) => {
  await openCampaign(page)
  await render(page, [
    {
      type: 'paragraph',
      data: { text: 'Explore our nature activities.' },
      tunes: { textAlign: 'right' },
    },
  ])
  const paragraph = page.locator('.ce-paragraph')
  await paragraph.scrollIntoViewIfNeeded()
  await paragraph.focus()
  await paragraph.evaluate((el) => {
    const range = document.createRange()
    range.selectNodeContents(el)
    const selection = window.getSelection()!
    selection.removeAllRanges()
    selection.addRange(range)
  })
  await paragraph.dispatchEvent('mouseup')
  const inline = page.locator('.ce-popover--inline.ce-popover--opened')
  await inline.getByRole('button', { name: 'Link', exact: true }).click()
  const options = inline.locator('.link-options-wrapper')
  await expect(options).toBeVisible()
  await expect
    .poll(() =>
      options.evaluate((el) => {
        const rect = el.getBoundingClientRect()
        const preview = document
          .querySelector('.pw-newsletter-design-preview')!
          .getBoundingClientRect()
        const x = Math.max(rect.left, preview.left) + 8
        const y = rect.top + rect.height / 2
        return x < rect.right && el.contains(document.elementFromPoint(x, y))
      }),
    )
    .toBe(true)
  await options
    .getByRole('combobox', { name: 'Style', exact: true })
    .selectOption({ label: 'Text link' })
})

test('campaign table options remain usable beside the live preview', async ({ page }) => {
  await openCampaign(page)
  await render(page, [
    { type: 'table', data: { content: [['First column', 'Last column']] } },
  ])
  const cell = page.locator('.tc-cell').last()
  await cell.scrollIntoViewIfNeeded()
  await cell.click()
  await cell.hover()
  await page.getByRole('button', { name: 'Column options', exact: true }).click()
  const menu = page.locator('.tc-toolbox--column .tc-popover--opened')
  await menu.getByRole('menuitemradio', { name: 'Align center', exact: true }).click()
  await expect(cell).toHaveCSS('text-align', 'center')
})

test('campaign editor and preview switches stay clickable below the sticky header', async ({
  page,
}) => {
  await openCampaign(page)
  await render(page, [
    {
      type: 'paragraph',
      data: { text: Array(60).fill('Newsletter content line.').join('<br>') },
    },
  ])
  const editor = page.locator('.pw-newsletter-design-editor')
  const previewButton = page.locator('[data-design-panel="preview"]')
  const editorButton = page.locator('[data-design-panel="settings"]')
  for (const width of [1199, 768, 375]) {
    await page.setViewportSize({ width, height: 812 })
    await page.locator('.ce-paragraph').scrollIntoViewIfNeeded()
    await expect
      .poll(() =>
        previewButton.evaluate((el) => {
          const rect = el.getBoundingClientRect()
          return el.contains(
            document.elementFromPoint(
              rect.left + rect.width / 2,
              rect.top + rect.height / 2,
            ),
          )
        }),
      )
      .toBe(true)
    await previewButton.click()
    await expect(editor).toBeHidden()
    await editorButton.click()
    await expect(editor).toBeVisible()
  }
})

test('automation preview width controls fit and remain clickable on a narrow screen', async ({
  page,
}) => {
  await page.setViewportSize({ width: 375, height: 812 })
  await page.goto('/admin/newsletter/automation/new')
  await page.locator('#Automation_audience').selectOption({ label: 'Localhost news (localhost.dev)' })
  // Criteria editors mount asynchronously and can move the sequence button during a click.
  await expect(page.locator('textarea[data-pw-criteria]:not(.pw-criteria-hidden)')).toHaveCount(0)
  await page.locator('.field-collection-add-button').first().click()
  const preview = page.locator('.pw-newsletter-step-preview').first()
  await preview.locator('summary').click()
  const desktop = preview.locator('[data-preview-width="desktop"]')
  await desktop.scrollIntoViewIfNeeded()
  await expect
    .poll(() =>
      desktop.evaluate((el) => {
        const rect = el.getBoundingClientRect()
        return (
          rect.left >= 0 &&
          rect.right <= innerWidth &&
          el.contains(
            document.elementFromPoint(
              rect.left + rect.width / 2,
              rect.top + rect.height / 2,
            ),
          )
        )
      }),
    )
    .toBe(true)
  await desktop.click()
  await expect(preview.locator('[data-pw-email-preview]')).toHaveAttribute(
    'data-viewport',
    'desktop',
  )
  await preview.locator('[data-preview-width="mobile"]').click()
  await expect(preview.locator('[data-pw-email-preview]')).toHaveAttribute(
    'data-viewport',
    'mobile',
  )
})
