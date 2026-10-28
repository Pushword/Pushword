import type { OutputBlockData } from '@editorjs/editorjs'
import type { Page } from '@playwright/test'
import { test, expect, render } from './fixtures'

const specimens: OutputBlockData[] = [
  { id: 'content', type: 'header', data: { text: 'Section <b>title</b>', level: 3 }, tunes: { anchor: 'section', textAlign: 'center' } },
  { id: 'content', type: 'paragraph', data: { text: 'Paragraph <b>text</b>' }, tunes: { anchor: 'paragraph', textAlign: 'right' } },
  { id: 'content', type: 'quote', data: { text: 'Quotation', caption: 'Author', alignment: 'left' }, tunes: { anchor: 'quotation' } },
  { id: 'content', type: 'list', data: { style: 'ordered', meta: { start: 3 }, items: [
    { content: 'Parent', meta: {}, items: [{ content: 'Child', meta: {}, items: [] }] },
    { content: 'Second', meta: {}, items: [] },
  ] }, tunes: { anchor: 'list' } },
]

async function snapshot(page: Page): Promise<OutputBlockData[]> {
  return page.evaluate(async () => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
    const editor = window.editors[holder.id]!
    return Promise.all(Array.from({ length: editor.blocks.getBlocksCount() }, async (_, index) => {
      const block = editor.blocks.getBlockByIndex(index)!
      const saved = await block.save()
      return { id: block.id, type: block.name, data: saved.data, tunes: saved.tunes }
    }))
  })
}

for (const blankType of ['paragraph', 'header']) {
  const blank = { id: 'blank', type: blankType, data: { text: blankType === 'paragraph' ? '&nbsp;' : '', level: 2 } }
  for (const key of ['Delete', 'Backspace']) {
    for (const specimen of specimens) {
      test(`${key} removes a blank ${blankType} before a ${specimen.type} without converting it`, async ({ editorPage: page }) => {
        await render(page, [blank, specimen])
        const original = (await snapshot(page))[1]!
        const field = page.locator(`[data-id="${key === 'Delete' ? 'blank' : 'content'}"] [contenteditable="true"]`).first()
        await field.click()
        await page.keyboard.press(key === 'Delete' ? 'End' : 'Home')
        await page.keyboard.press(key)
        await expect(page.locator('.ce-block')).toHaveCount(1)
        await expect(page.locator('[data-id="content"] [contenteditable="true"]').first()).toBeFocused()
        expect(await snapshot(page)).toEqual([original])
      })
    }

    test(`${key} of a blank ${blankType} preserves both blocks through undo and redo`, async ({ editorPage: page }) => {
      await render(page, [blank, specimens[0]!])
      const original = await snapshot(page)
      await page.locator(`[data-id="${key === 'Delete' ? 'blank' : 'content'}"] [contenteditable="true"]`).first().click()
      await page.keyboard.press(key === 'Delete' ? 'End' : 'Home')
      await page.keyboard.press(key)
      await expect(page.locator('.ce-block')).toHaveCount(1)
      await page.waitForTimeout(650)
      await page.keyboard.press('Control+z')
      await expect(page.locator('.ce-block')).toHaveCount(2)
      expect(await snapshot(page)).toEqual(original)
      await page.keyboard.press('Control+y')
      await expect(page.locator('.ce-block')).toHaveCount(1)
      expect(await snapshot(page)).toEqual([original[1]])
    })
  }
}

for (const key of ['Delete', 'Backspace']) {
  test(`${key} retains the following heading when both blocks are blank`, async ({ editorPage: page }) => {
    await render(page, [
      { id: 'blank', type: 'paragraph', data: { text: '&nbsp;' } },
      { id: 'content', type: 'header', data: { text: '', level: 3 }, tunes: { anchor: 'section', textAlign: 'center' } },
    ])
    const original = (await snapshot(page))[1]!
    await page.locator(`[data-id="${key === 'Delete' ? 'blank' : 'content'}"] [contenteditable="true"]`).first().click()
    await page.keyboard.press(key === 'Delete' ? 'End' : 'Home')
    await page.keyboard.press(key)
    await expect(page.locator('.ce-block')).toHaveCount(1)
    await expect(page.locator('h3.ce-header')).toBeFocused()
    expect(await snapshot(page)).toEqual([original])
  })

  test(`${key} removes a trailing blank heading and retains the preceding heading's level and attributes`, async ({ editorPage: page }) => {
    await render(page, [specimens[0]!, { id: 'blank', type: 'header', data: { text: '', level: 2 } }])
    const original = (await snapshot(page))[0]!
    await page.locator(`[data-id="${key === 'Delete' ? 'content' : 'blank'}"] .ce-header`).click()
    await page.keyboard.press(key === 'Delete' ? 'End' : 'Home')
    await page.keyboard.press(key)
    await expect(page.locator('.ce-block')).toHaveCount(1)
    await expect(page.locator('h3.ce-header')).toBeFocused()
    expect(await snapshot(page)).toEqual([original])
  })
}

test('Backspace inside a quote caption keeps the quote and the preceding blank heading', async ({ editorPage: page }) => {
  await render(page, [{ type: 'header', data: { text: '', level: 2 } }, specimens[2]!])
  const original = await snapshot(page)
  await page.locator('.cdx-quote__caption').click()
  await page.keyboard.press('Home')
  await page.keyboard.press('Backspace')
  await expect(page.locator('.ce-block')).toHaveCount(2)
  expect(await snapshot(page)).toEqual(original)
})
