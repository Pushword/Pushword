import { test, expect, render } from './fixtures'
import type { Page } from '@playwright/test'

async function openCampaign(page: Page): Promise<void> {
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('/admin/newsletter/campaign/new')
  await page.locator('#Campaign_audience').selectOption({ label: 'Localhost news' })
  await page.waitForFunction(() => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')
    return holder && window.editors?.[holder.id]
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

test('campaign table options remain usable beside the live preview', async ({
  page,
}) => {
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
