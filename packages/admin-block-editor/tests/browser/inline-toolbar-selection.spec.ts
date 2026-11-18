import type { Page } from '@playwright/test'
import { test, expect, render } from './fixtures'

async function point(page: Page, offset: number): Promise<{ x: number; y: number }> {
  return page.locator('.ce-paragraph').evaluate((field, offset) => {
    const range = document.createRange()
    range.setStart(field.firstChild!, offset)
    range.collapse(true)
    const box = range.getBoundingClientRect()
    return { x: box.x + 0.1, y: box.y + box.height / 2 }
  }, offset)
}

for (const backward of [false, true]) {
  for (const trailingSpace of [false, true]) {
    test(`the inline toolbar stays available after a paused ${backward ? 'backward' : 'forward'} drag ${trailingSpace ? 'with' : 'without'} a trailing space`, async ({ editorPage: page }) => {
      await render(page, [{ type: 'paragraph', data: { text: 'Voici deux mots dans ce paragraphe.' } }])
      await page.locator('.ce-paragraph').scrollIntoViewIfNeeded()
      const points = [await point(page, 6), await point(page, trailingSpace ? 16 : 15)]
      if (backward) points.reverse()
      await page.mouse.move(points[0]!.x, points[0]!.y)
      await page.mouse.down()
      await page.mouse.move(points[1]!.x, points[1]!.y, { steps: 20 })
      // Let Editor.js finish its selectionchange debounce before releasing the mouse.
      await page.waitForTimeout(350)
      await page.mouse.up()
      const toolbar = page.locator('.ce-inline-toolbar')
      await expect(toolbar.locator('[data-item-name="bold"]')).toBeVisible()
      await expect(toolbar.locator('[data-item-name="italic"]')).toBeVisible()
      await expect(toolbar.locator('[data-item-name="link"]')).toBeVisible()
      await toolbar.locator('[data-item-name="bold"]').click()
      await expect(page.locator('.ce-paragraph b, .ce-paragraph strong')).toHaveText(trailingSpace ? 'deux mots ' : 'deux mots')
      await expect(page.locator('.ce-paragraph')).toHaveText('Voici deux mots dans ce paragraphe.')
    })
  }
}
