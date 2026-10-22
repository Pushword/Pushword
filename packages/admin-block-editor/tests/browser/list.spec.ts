import { test, expect, render, saveAndReload } from './fixtures'
import type { Page } from '@playwright/test'

const item = (content: string, items: any[] = []) => ({ content, meta: {}, items })
const list = (...items: any[]) => ({ type: 'list', data: { style: 'unordered', meta: {}, items } })

/** The list as indented lines, one per item, read from the rendered blocks. */
function outline(page: Page): Promise<string[]> {
  return page.evaluate(() => Array.from(document.querySelectorAll('.cdx-list__item-content'), (field) => {
    let depth = 0
    for (let item = field.closest('.cdx-list__item')!.parentElement!.closest('.cdx-list__item'); item; item = item.parentElement!.closest('.cdx-list__item')) depth++
    return '  '.repeat(depth) + field.textContent
  }))
}

/** Put content on the system clipboard from a page of the same origin, within a click. */
async function copyToClipboard(page: Page, data: Record<string, string>): Promise<void> {
  const source = await page.context().newPage()
  await source.goto('/login')
  await source.setContent('<button type="button">Copy</button>')
  await source.evaluate((data) => {
    document.querySelector('button')!.addEventListener('click', async () => {
      await navigator.clipboard.write([new ClipboardItem(Object.fromEntries(
        Object.entries(data).map(([type, value]) => [type, new Blob([value], { type })]),
      ))])
      document.body.dataset.copied = '1'
    })
  }, data)
  await source.locator('button').click()
  await expect(source.locator('body')).toHaveAttribute('data-copied', '1')
  await source.close()
  await page.bringToFront()
}

async function newItemAfter(page: Page, text: string): Promise<void> {
  await page.locator('.cdx-list__item-content').filter({ hasText: text }).click()
  await page.keyboard.press('End')
  await page.keyboard.press('Enter')
}

const wordBullet = (level: number, text: string) =>
  `<p class="MsoListParagraph" style="mso-list:l0 level${level} lfo1"><span style="font-family:Symbol;mso-list:Ignore">·<span>&nbsp;&nbsp;&nbsp;</span></span>${text}</p>`

test('Word bullets pasted into an item join its list at their levels, and Tab nests them', async ({ editorPage: page }) => {
  await render(page, [list(item('Extras', [item('Price')]), item('Luggage')), { type: 'paragraph', data: { text: 'After.' } }])
  await copyToClipboard(page, {
    'text/html': wordBullet(1, 'Option <b>A</b>') + wordBullet(2, 'Detail') + wordBullet(1, 'Option B'),
    'text/plain': '·   Option A\n·   Detail\n·   Option B',
  })
  await newItemAfter(page, 'Luggage')
  await page.keyboard.press('Control+v')
  await expect.poll(() => outline(page)).not.toContain('')
  expect(await outline(page)).toEqual(['Extras', '  Price', 'Luggage', 'Option A', '  Detail', 'Option B'])
  await expect(page.locator('.ce-block')).toHaveCount(2)
  await expect(page.locator('.cdx-list__item-content b')).toHaveText('A')
  await page.keyboard.press('Home')
  await page.keyboard.press('Tab')
  await expect(page.locator('.cdx-list__item-content').filter({ hasText: 'Option B' })).toBeFocused()
  expect(await outline(page)).toEqual(['Extras', '  Price', 'Luggage', 'Option A', '  Detail', '  Option B'])
  await saveAndReload(page)
  expect(await outline(page)).toEqual(['Extras', '  Price', 'Luggage', 'Option A', '  Detail', '  Option B'])
})

for (const [source, data, expected] of [
  ['plain lines', { 'text/plain': 'Line one\nLine two\nLine three' }, ['Line one', 'Line two', 'Line three']],
  ['a nested HTML list', { 'text/plain': 'Parent\nChild', 'text/html': '<ul><li>Parent<ul><li><b>Child</b></li></ul></li></ul>' }, ['Parent', '  Child']],
] as const) {
  test(`${source} pasted from the clipboard into an item become items there`, async ({ editorPage: page }) => {
    await render(page, [list(item('First'), item('Last'))])
    await copyToClipboard(page, data)
    await newItemAfter(page, 'First')
    await page.keyboard.press('Control+v')
    await expect.poll(() => outline(page)).toEqual(['First', ...expected, 'Last'])
    await expect(page.locator('.ce-block')).toHaveCount(1)
    await page.keyboard.type('!')
    await expect(page.locator('.cdx-list__item-content').nth(expected.length)).toHaveText(`${expected[expected.length - 1]!.trim()}!`)
    await saveAndReload(page)
    expect(await outline(page)).toEqual(['First', ...expected.slice(0, -1), `${expected[expected.length - 1]}!`, 'Last'])
  })
}

test('one line pasted into an item stays inline text', async ({ editorPage: page }) => {
  await render(page, [list(item('Length'), item('Last'))])
  await copyToClipboard(page, { 'text/plain': '12km' })
  await page.locator('.cdx-list__item-content').first().click()
  await page.keyboard.press('End')
  await page.keyboard.press('Control+v')
  await expect.poll(() => outline(page)).toEqual(['Length12km', 'Last'])
})

test('Delete at the end of an item brings the next one up with its children', async ({ editorPage: page }) => {
  await render(page, [list(item('Single room'), item('<b>210</b> per night', [item('Child')]), item('Luggage'))])
  await page.locator('.cdx-list__item-content').first().click()
  await page.keyboard.press('End')
  await page.keyboard.press('Delete')
  await expect.poll(() => outline(page)).toEqual(['Single room210 per night', '  Child', 'Luggage'])
  await page.keyboard.type('!')
  await expect(page.locator('.cdx-list__item-content').first()).toHaveText('Single room!210 per night')
  await saveAndReload(page)
  expect(await outline(page)).toEqual(['Single room!210 per night', '  Child', 'Luggage'])
  await expect(page.locator('.cdx-list__item-content b')).toHaveText('210')
})

const highlighted = [
  { type: 'paragraph', data: { text: 'Intro.' } },
  list(item('First'), item('Second')),
  { type: 'header', data: { text: 'Next day', level: 2 } },
  { type: 'paragraph', data: { text: 'After.' } },
]

async function highlightListAndHeading(page: Page): Promise<void> {
  await page.locator('.cdx-list__item-content').first().click()
  await page.keyboard.press('Home')
  await page.keyboard.press('Shift+ArrowDown')
  await page.keyboard.press('Shift+ArrowDown')
  await expect(page.locator('.ce-block--selected')).toHaveCount(2)
}

for (const key of ['Delete', 'Backspace']) {
  test(`${key} removes highlighted blocks though the caret is still in an item`, async ({ editorPage: page }) => {
    await render(page, highlighted)
    await highlightListAndHeading(page)
    await page.keyboard.press(key)
    await expect(page.locator('.cdx-list')).toHaveCount(0)
    await expect(page.locator('.ce-header')).toHaveCount(0)
    await expect(page.locator('.ce-paragraph')).toHaveText(['Intro.', '', 'After.'])
    await page.keyboard.type('x')
    await saveAndReload(page)
    await expect(page.locator('.ce-paragraph')).toHaveText(['Intro.', 'x', 'After.'])
  })
}

test('highlighted blocks copied by Editor.js give one plain line per item, without the heading levels', async ({ editorPage: page }) => {
  await render(page, highlighted)
  // Without the Clipboard API (an insecure page, or another holder), Editor.js copies the blocks itself.
  await page.evaluate(() => { delete (window as { ClipboardItem?: unknown }).ClipboardItem })
  await highlightListAndHeading(page)
  await page.keyboard.press('Control+c')
  await page.evaluate(() => document.body.append(Object.assign(document.createElement('textarea'), { id: 'paste-target' })))
  await page.locator('#paste-target').click()
  await page.keyboard.press('Control+v')
  await expect(page.locator('#paste-target')).toHaveValue('First\nSecond\n\nNext day')
})
