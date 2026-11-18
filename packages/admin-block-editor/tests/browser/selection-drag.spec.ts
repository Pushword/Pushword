import type { Page } from '@playwright/test'
import { test, expect, render } from './fixtures'

const paragraphs = ['First paragraph to select.', 'Second paragraph to select.', 'Third paragraph stays here.']

async function prepare(page: Page): Promise<void> {
  await render(page, paragraphs.map(text => ({ type: 'paragraph', data: { text } })))
  await page.locator('.ce-paragraph').first().scrollIntoViewIfNeeded()
}

async function point(page: Page, paragraph: number, offset: number): Promise<{ x: number; y: number; right: number }> {
  return page.locator('.ce-paragraph').nth(paragraph).evaluate((field, offset) => {
    const range = document.createRange()
    range.setStart(field.firstChild!, offset)
    range.setEnd(field.firstChild!, offset + 1)
    const box = range.getBoundingClientRect()
    return { x: box.x + 0.25, y: box.y + box.height / 2, right: box.right }
  }, offset)
}

async function highlighted(page: Page): Promise<string[]> {
  return page.evaluate(() => Array.from(CSS.highlights.get('pw-text-selection') ?? [], range => range.toString()))
}

test('a fully selected paragraph has a block highlight without losing native text selection', async ({ editorPage: page }) => {
  await prepare(page)
  const start = await point(page, 0, 0)
  await page.mouse.click(start.x + 5, start.y, { clickCount: 3 })
  await expect.poll(() => page.evaluate(() => getSelection()!.toString())).toBe(paragraphs[0])
  await expect(page.locator('.ce-block').first()).toHaveClass(/pw-text-selection-block/)
  await page.keyboard.type('Replacement')
  await expect(page.locator('.ce-paragraph')).toHaveText(['Replacement', ...paragraphs.slice(1)])
})

for (const backward of [false, true]) {
  test(`fully selected boundary paragraphs receive block highlights when dragging ${backward ? 'backward' : 'forward'}`, async ({ editorPage: page }) => {
    await prepare(page)
    const start = await point(page, 0, 0)
    const end = await point(page, 2, paragraphs[2]!.length - 1)
    const points = [{ x: start.x, y: start.y }, { x: end.right + 2, y: end.y }]
    if (backward) points.reverse()
    await page.mouse.move(points[0]!.x, points[0]!.y)
    await page.mouse.down()
    await page.mouse.move(points[1]!.x, points[1]!.y, { steps: 30 })
    await page.mouse.up()
    await expect(page.locator('.pw-text-selection-block')).toHaveCount(3)
    await expect.poll(() => highlighted(page)).toEqual(paragraphs)
  })
  test(`dragging ${backward ? 'backward' : 'forward'} through the insertion gap does not select the entire next paragraph`, async ({ editorPage: page }) => {
    await prepare(page)
    const start = await point(page, backward ? 1 : 0, 6)
    const end = await point(page, backward ? 0 : 1, 6)
    const insertion = (await page.locator('.pw-block-insertion').first().boundingBox())!
    await page.mouse.move(start.x, start.y)
    await page.mouse.down()
    await page.mouse.move(end.x, insertion.y + insertion.height / 2, { steps: 15 })
    if (backward) {
      await expect.poll(() => page.evaluate(() => getSelection()!.toString())).toBe(paragraphs[1]!.slice(0, 6))
      expect(await highlighted(page)).toEqual([])
    } else await expect.poll(() => highlighted(page)).toEqual([paragraphs[0]!.slice(6), ''])
    await page.mouse.move(end.x, end.y, { steps: 15 })
    await expect.poll(() => highlighted(page)).toEqual([paragraphs[0]!.slice(6), paragraphs[1]!.slice(0, 6)])
    const returned = await point(page, backward ? 1 : 0, 11)
    await page.mouse.move(returned.x, returned.y, { steps: 15 })
    await page.mouse.up()
    await expect.poll(() => highlighted(page)).toEqual([])
    expect(await page.evaluate(() => getSelection()!.toString())).toBe(paragraphs[backward ? 1 : 0]!.slice(6, 11))
    await expect(page.locator('.pw-text-selection-block')).toHaveCount(0)
    await expect(page.locator('.ce-paragraph')).toHaveText(paragraphs)
  })
}
