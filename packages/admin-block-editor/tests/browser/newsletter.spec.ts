import { test, expect, render } from './fixtures'
import type { Page } from '@playwright/test'

async function openCampaign(page: Page): Promise<void> {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('/admin/newsletter/campaign/new')
  await page.locator('#Campaign_audience').selectOption({ label: 'Localhost news' })
  await page.waitForFunction(() => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')
    return holder && window.editors?.[holder.id]?.blocks
  })
  await expect(page.locator('.pw-newsletter-design-canvas')).toBeVisible()
}

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
  await page.locator('#Automation_audience').selectOption({ label: 'Localhost news' })
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
