import { test, expect, render, saveAndReload } from './fixtures'
import type { OutputBlockData } from '@editorjs/editorjs'

const raw = { type: 'raw', data: { html: '<div>Keep raw</div>' } }
const table = { type: 'table', data: { content: [['Keep cell']] } }
const samples: OutputBlockData[] = [
  raw, table,
  { type: 'codeBlock', data: { html: 'Keep code', language: 'html' } },
  { type: 'quote', data: { text: 'Quote', caption: 'Author', alignment: 'left' } },
  { type: 'notice', data: { text: 'Notice', title: 'Title', level: 'note' } },
  { type: 'delimiter', data: {} },
  { type: 'groupStart', data: { anchor: '', class: '', collapsible: false, legacy: false } },
  { type: 'groupEnd', data: { collapsible: false, legacy: false, args: '' } },
  { type: 'image', data: { media: '1.jpg', caption: 'Caption' } },
  { type: 'gallery', data: { items: [{ media: '1.jpg', caption: 'Caption' }] } },
  { type: 'attaches', data: { title: 'Attachment', file: { media: '1.jpg', size: 0 } } },
  { type: 'pages_list', data: { kw: 'children' } },
  { type: 'embed', data: { serviceUrl: 'https://www.youtube.com/watch?v=test', media: '1.jpg', alternativeText: 'Video' } },
  { type: 'card_list', data: { items: [{ title: 'Card', description: 'Description' }] } },
  { type: 'snippet', data: { name: 'custom', params: {} } },
  { type: 'quiz', data: {} },
]

test('all sixteen non-flowing block types offer insertion on either side', async ({ editorPage: page }) => {
  for (const sample of samples) {
    await test.step(sample.type, async () => {
      await render(page, [raw, sample, raw])
      await expect(page.locator('.ce-block')).toHaveCount(3)
      await expect(page.getByRole('button', { name: 'Add paragraph', exact: true })).toHaveCount(2)
    })
  }
})

test('paragraphs, headings and lists do not get extra insertion controls', async ({ editorPage: page }) => {
  for (const sample of [
    { type: 'paragraph', data: { text: 'Text' } },
    { type: 'header', data: { text: 'Heading', level: 2 } },
    { type: 'list', data: { style: 'unordered', items: [{ content: 'Item', meta: {}, items: [] }] } },
  ]) {
    await render(page, [raw, sample, table])
    await expect(page.locator('.pw-block-insertion')).toHaveCount(0)
  }
})

test('hover reveals the separator without moving content and clicking inserts persistent text', async ({ editorPage: page }) => {
  await render(page, [raw, table])
  const button = page.getByRole('button', { name: 'Add paragraph', exact: true })
  await page.mouse.move(0, 0)
  await expect(button).toHaveCSS('opacity', '0')
  const before = await page.locator('.tc-wrap').boundingBox()
  await button.hover()
  await expect(button).toHaveCSS('opacity', '1')
  await expect(page.locator('.ce-toolbar')).toHaveCSS('visibility', 'hidden')
  expect(await page.locator('.tc-wrap').boundingBox()).toEqual(before)
  await button.click()
  await expect(page.locator('.ce-paragraph')).toBeFocused()
  await expect(button).toHaveCount(0)
  await page.keyboard.type('Between the blocks')
  await saveAndReload(page)
  await expect(page.locator('.ce-block')).toHaveCount(3)
  await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toHaveText('Between the blocks')
  await expect(page.locator('.tc-cell')).toHaveText('Keep cell')
})

for (const key of ['Enter', 'Space']) {
  test(`keyboard focus reveals the separator and ${key} inserts a paragraph`, async ({ editorPage: page }) => {
    await render(page, [table, raw])
    const button = page.getByRole('button', { name: 'Add paragraph', exact: true })
    await button.focus()
    await expect(button).toHaveCSS('opacity', '1')
    await page.keyboard.press(key)
    await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toBeFocused()
  })
}

test('insertion and its separator follow undo, redo and moving blocks', async ({ editorPage: page }) => {
  await render(page, [raw, table])
  await page.getByRole('button', { name: 'Add paragraph', exact: true }).click()
  await expect(page.locator('.ce-paragraph')).toBeFocused()
  await page.keyboard.press('Control+z')
  await expect(page.locator('.ce-block')).toHaveCount(2)
  await expect(page.locator('.pw-block-insertion')).toHaveCount(1)
  await page.keyboard.press('Control+y')
  await expect(page.locator('.ce-block')).toHaveCount(3)
  await expect(page.locator('.pw-block-insertion')).toHaveCount(0)
  await page.evaluate(() => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
    window.editors[holder.id]!.blocks.move(0, 1)
  })
  await expect(page.locator('.pw-block-insertion')).toHaveCount(1)
})

test('clicking the separator after selecting blocks keeps their content', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Keep introduction' } }, raw, table])
  await page.locator('.ce-paragraph').click()
  await page.keyboard.press('Control+a')
  await page.keyboard.press('Control+a')
  await expect(page.locator('.ce-block--selected')).toHaveCount(3)
  await page.getByRole('button', { name: 'Add paragraph', exact: true }).click()
  await page.keyboard.type('New text')
  await expect(page.locator('.ce-block')).toHaveCount(4)
  await expect(page.locator('.ce-paragraph')).toHaveText(['Keep introduction', 'New text'])
  await expect(page.locator('.tc-cell')).toHaveText('Keep cell')
  await expect(page.locator('.ce-block--selected')).toHaveCount(0)
})
