import type { OutputBlockData } from '@editorjs/editorjs'
import { test, expect, render, saveAndReload } from './fixtures'

const heading = {
  id: 'heading',
  type: 'header',
  data: { text: 'Section <b>title</b>', level: 3 },
  tunes: { anchor: 'section-title', textAlign: 'center' },
}

for (const [name, text] of [
  ['empty', ''], ['line break', '<br>'], ['space', ' '], ['nonbreaking space', '&nbsp;'],
  ['formatted whitespace', '<b>&nbsp;</b>'], ['zero width spaces', '\u200b\u200b'], ['BOM', '\uFEFF'],
]) {
  test(`Delete from a ${name} paragraph preserves the following heading`, async ({ editorPage: page }) => {
    await render(page, [{ id: 'empty', type: 'paragraph', data: { text } }, heading])
    await page.locator('.ce-paragraph').click()
    await page.keyboard.press('End')
    await page.keyboard.press('Delete')
    await expect(page.locator('.ce-block')).toHaveCount(1)
    await expect(page.locator('h3.ce-header')).toBeFocused()
    expect(await page.evaluate(async () => {
      const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
      return (await window.editors[holder.id]!.save()).blocks
    })).toEqual([heading])
  })
}

test('the preserved heading keeps its level and attributes after saving and reloading', async ({ editorPage: page }) => {
  page.setDefaultTimeout(30_000)
  await render(page, [{ type: 'paragraph', data: { text: '&nbsp;' } }, heading])
  await page.locator('.ce-paragraph').click()
  await page.keyboard.press('End')
  await page.keyboard.press('Delete')
  await expect(page.locator('h3.ce-header')).toBeFocused()
  await saveAndReload(page)
  await expect(page.locator('.ce-block')).toHaveCount(1)
  await expect(page.locator('h3.ce-header b')).toHaveText('title')
  expect(await page.evaluate(async () => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
    return (await window.editors[holder.id]!.save()).blocks.map(({ id, ...block }: OutputBlockData) => block)
  })).toEqual([{ type: heading.type, data: heading.data, tunes: heading.tunes }])
})

test('Delete of a blank paragraph preserves the heading through undo and redo', async ({ editorPage: page }) => {
  await render(page, [{ id: 'empty', type: 'paragraph', data: { text: '&nbsp;' } }, heading])
  await page.locator('.ce-paragraph').click()
  await page.keyboard.press('End')
  await page.keyboard.press('Delete')
  await expect(page.locator('.ce-block')).toHaveCount(1)
  // Let the deletion settle as its own history entry before undoing it.
  await page.waitForTimeout(650)
  await page.keyboard.press('Control+z')
  await expect(page.locator('.ce-block')).toHaveCount(2)
  await expect(page.locator('.ce-paragraph')).toHaveText('\u00a0')
  await expect(page.locator('h3.ce-header b')).toHaveText('title')
  await page.keyboard.press('Control+y')
  await expect(page.locator('.ce-block')).toHaveCount(1)
  await expect(page.locator('h3.ce-header')).toBeFocused()
})

test('Delete from a nonempty paragraph still merges text at the block boundary', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Before ' } }, heading])
  await page.locator('.ce-paragraph').click()
  await page.keyboard.press('End')
  await page.keyboard.press('Delete')
  await expect(page.locator('.ce-block')).toHaveCount(1)
  await expect(page.locator('.ce-paragraph')).toHaveText('Before Section title')
})
