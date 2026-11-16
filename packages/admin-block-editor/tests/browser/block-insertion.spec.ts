import { test, expect, render, saveAndReload } from './fixtures'
import type { OutputBlockData } from '@editorjs/editorjs'

const raw = { type: 'raw', data: { html: '<div>Keep raw</div>' } }
const table = { type: 'table', data: { content: [['Keep cell']] } }
const samples: OutputBlockData[] = [
  raw, table,
  { type: 'paragraph', data: { text: 'Paragraph' } },
  { type: 'header', data: { text: 'Heading', level: 2 } },
  { type: 'list', data: { style: 'unordered', items: [{ content: 'Item', meta: {}, items: [] }] } },
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

test('all nineteen block types offer insertion on either side', async ({ editorPage: page }) => {
  for (const sample of samples) {
    await test.step(sample.type, async () => {
      await render(page, [raw, sample, raw])
      await expect(page.locator('.ce-block')).toHaveCount(3)
      await expect(page.getByRole('button', { name: 'Add paragraph', exact: true })).toHaveCount(2)
    })
  }
})

test('ordinary text blocks offer the same insertion control', async ({ editorPage: page }) => {
  for (const sample of [
    { type: 'paragraph', data: { text: 'Text' } },
    { type: 'header', data: { text: 'Heading', level: 2 } },
    { type: 'list', data: { style: 'unordered', items: [{ content: 'Item', meta: {}, items: [] }] } },
  ]) {
    await render(page, [sample, sample])
    await expect(page.locator('.pw-block-insertion')).toHaveCount(1)
    await page.getByRole('button', { name: 'Add paragraph', exact: true }).click()
    await expect(page.locator('.ce-block')).toHaveCount(3)
    await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toBeFocused()
  }
})

for (const specimen of [
  { name: 'raw', block: raw },
  { name: 'Mermaid', block: { type: 'codeBlock', data: { html: 'graph TD\nA --> B', language: 'mermaid' } } },
]) {
  for (const level of [2, 3]) {
    for (const headingFirst of [false, true]) {
      test(`${specimen.name} ${headingFirst ? 'after' : 'before'} H${level}: insert and save a paragraph without altering either block`, async ({ editorPage: page }) => {
        const heading = { type: 'header', data: { text: `Keep heading H${level}`, level } }
        await render(page, headingFirst ? [heading, specimen.block] : [specimen.block, heading])
        if (specimen.name === 'Mermaid') {
          await expect(page.locator('.pw-mermaid-preview svg')).toBeVisible()
        }
        const before: OutputBlockData[] = await page.evaluate(async () => {
          const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
          return (await window.editors[holder.id]!.save()).blocks
        })
        await page.getByRole('button', { name: 'Add paragraph', exact: true }).click()
        await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toBeFocused()
        await page.keyboard.type('Between code and heading')
        await saveAndReload(page)
        await expect(page.locator(`h${level}.ce-header`)).toHaveText(`Keep heading H${level}`)
        const after: OutputBlockData[] = await page.evaluate(async () => {
          const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
          return (await window.editors[holder.id]!.save()).blocks
        })
        expect(after.map(({ type, data }) => ({ type, data }))).toEqual([
          { type: before[0]!.type, data: before[0]!.data },
          { type: 'paragraph', data: { text: 'Between code and heading' } },
          { type: before[1]!.type, data: before[1]!.data },
        ])
      })
    }
  }
}

test('hover reveals the separator without moving content and clicking inserts persistent text', async ({ editorPage: page }) => {
  await render(page, [raw, table])
  const button = page.getByRole('button', { name: 'Add paragraph', exact: true })
  const separator = page.locator('.pw-block-insertion')
  await page.mouse.move(0, 0)
  await expect(separator).toHaveCSS('opacity', '0')
  const before = await page.locator('.tc-wrap').boundingBox()
  await button.hover()
  await expect(separator).toHaveCSS('opacity', '1')
  await expect(page.locator('.ce-toolbar')).toHaveCSS('visibility', 'hidden')
  expect(await page.locator('.tc-wrap').boundingBox()).toEqual(before)
  await button.click()
  await expect(page.locator('.ce-paragraph')).toBeFocused()
  await expect(button).toHaveCount(2)
  await page.keyboard.type('Between the blocks')
  await saveAndReload(page)
  await expect(page.locator('.ce-block')).toHaveCount(3)
  await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toHaveText('Between the blocks')
  await expect(page.locator('.tc-cell')).toHaveText('Keep cell')
})

test('the decorative rule does not insert a paragraph; its labelled button does', async ({ editorPage: page }) => {
  await render(page, [raw, table])
  const separator = page.locator('.pw-block-insertion')
  await separator.click({ position: { x: 4, y: 16 } })
  await expect(page.locator('.ce-block')).toHaveCount(2)
  const button = page.getByRole('button', { name: 'Add paragraph', exact: true })
  await expect(button).toHaveText('')
  await expect(button).toHaveAttribute('title', 'Add paragraph')
  await button.click()
  await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toBeFocused()
})

for (const key of ['Enter', 'Space']) {
  test(`keyboard focus reveals the separator and ${key} inserts a paragraph`, async ({ editorPage: page }) => {
    await render(page, [table, raw])
    const button = page.getByRole('button', { name: 'Add paragraph', exact: true })
    await button.focus()
    await expect(page.locator('.pw-block-insertion')).toHaveCSS('opacity', '1')
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
  await expect(page.locator('.pw-block-insertion')).toHaveCount(2)
  await page.evaluate(() => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
    window.editors[holder.id]!.blocks.move(0, 1)
  })
  await expect(page.locator('.pw-block-insertion')).toHaveCount(2)
})

test('clicking the separator after selecting blocks keeps their content', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Keep introduction' } }, raw, table])
  await page.locator('.ce-paragraph').click()
  await page.keyboard.press('Control+a')
  await page.keyboard.press('Control+a')
  await expect(page.locator('.ce-block--selected')).toHaveCount(3)
  await page.getByRole('button', { name: 'Add paragraph', exact: true }).last().click()
  await page.keyboard.type('New text')
  await expect(page.locator('.ce-block')).toHaveCount(4)
  await expect(page.locator('.ce-paragraph')).toHaveText(['Keep introduction', 'New text'])
  await expect(page.locator('.tc-cell')).toHaveText('Keep cell')
  await expect(page.locator('.ce-block--selected')).toHaveCount(0)
})
