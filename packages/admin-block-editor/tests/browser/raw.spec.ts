import { test, expect, render, saveAndReload } from './fixtures'
import type { OutputBlockData } from '@editorjs/editorjs'

const rawBlocks = [
  { id: 'first-raw', type: 'raw', data: { html: '<div>First raw</div>' } },
  { id: 'second-raw', type: 'raw', data: { html: '<div>Second raw</div>' } },
]

for (const type of ['raw', 'codeBlock']) {
  test(`${type}: Enter keeps a code newline and Ctrl+Enter creates a paragraph`, async ({ editorPage: page }) => {
    await render(page, [{ type, data: { html: 'First line', language: 'html' } }])
    const raw = page.locator('.editorjs-monaco-wrapper')
    await expect(raw.locator('.monaco-editor')).toBeVisible()
    await raw.click()
    await page.keyboard.press('Control+End')
    await page.keyboard.press('Enter')
    await page.keyboard.type('Second line')
    await expect(page.locator('.ce-block')).toHaveCount(1)
    await page.keyboard.press('Control+Enter')
    await expect(page.locator('.ce-paragraph')).toBeFocused()
    const blocks = await page.evaluate(async () => {
      const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
      return (await window.editors[holder.id]!.save()).blocks
    })
    expect(blocks[0].data.html.split(/\r?\n/)).toEqual(['First line', 'Second line'])
  })
}

test('multi-block selection visibly includes opaque raw editors', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'Select all these blocks' } },
    ...rawBlocks,
  ])
  const raw = page.locator('.editorjs-monaco-wrapper').first()
  await expect(raw.locator('.monaco-editor')).toBeVisible()
  await page.locator('.ce-paragraph').click()
  await page.keyboard.press('Control+a')
  await page.keyboard.press('Control+a')
  await expect(page.locator('.ce-block--selected')).toHaveCount(3)
  for (const wrapper of await page.locator('.editorjs-monaco-wrapper').all()) {
    await expect(wrapper).toHaveCSS('outline-style', 'solid')
    await expect(wrapper).toHaveCSS('outline-width', '2px')
  }
  await raw.click()
  await expect(page.locator('.ce-block--selected')).toHaveCount(0)
  await expect(raw).toHaveCSS('outline-style', 'none')
})

for (const direction of ['before', 'after'] as const) {
  test(`raw menu inserts a paragraph ${direction} the block between two raw blocks`, async ({ editorPage: page }) => {
    await render(page, rawBlocks)
    const subject = page.locator(`[data-id="${direction === 'after' ? 'first-raw' : 'second-raw'}"]`)
    await subject.hover()
    await page.locator('.ce-toolbar__settings-btn').click()
    await page.getByText(`Add paragraph ${direction}`, { exact: true }).click()
    const paragraph = page.locator('.ce-paragraph')
    await expect(paragraph).toBeFocused()
    await page.keyboard.type('Between raw blocks')
    await saveAndReload(page)
    await expect(page.locator('.ce-block')).toHaveCount(3)
    await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toHaveText('Between raw blocks')
    await expect(page.locator('.editorjs-monaco-wrapper')).toHaveCount(2)
  })

  test(`Ctrl+${direction === 'before' ? 'Shift+' : ''}Enter leaves raw code intact and inserts a paragraph ${direction}`, async ({ editorPage: page }) => {
    await render(page, rawBlocks)
    const raw = page.locator(`[data-id="${direction === 'after' ? 'first-raw' : 'second-raw'}"] .editorjs-monaco-wrapper`)
    await expect(raw.locator('.monaco-editor')).toBeVisible()
    await raw.click()
    await page.keyboard.press(`Control+${direction === 'before' ? 'Shift+' : ''}Enter`)
    await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toBeFocused()
    await page.keyboard.type('Between raw blocks')
    const blocks: OutputBlockData[] = await page.evaluate(async () => {
      const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
      return (await window.editors[holder.id]!.save()).blocks
    })
    expect(blocks.map(block => ({ type: block.type, data: block.data }))).toEqual([
      { type: 'raw', data: rawBlocks[0]!.data },
      { type: 'paragraph', data: { text: 'Between raw blocks' } },
      { type: 'raw', data: rawBlocks[1]!.data },
    ])
    await page.keyboard.press('Control+z')
    await expect(page.locator('.ce-paragraph')).toHaveText('')
    await page.keyboard.press('Control+z')
    await expect(page.locator('.ce-paragraph')).toHaveCount(0)
    await expect(page.locator('.editorjs-monaco-wrapper')).toHaveCount(2)
    await page.keyboard.press('Control+y')
    await expect(page.locator('.ce-paragraph')).toHaveText('')
    await page.keyboard.press('Control+y')
    await expect(page.locator('.ce-paragraph')).toHaveText('Between raw blocks')
  })
}
