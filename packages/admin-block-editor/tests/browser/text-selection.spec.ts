import type { Page } from '@playwright/test'
import { test, expect, render, saveAndReload } from './fixtures'

const paragraph = (id: string, text: string) => ({ id, type: 'paragraph', data: { text } })
const item = (content: string, items: any[] = []) => ({ content, meta: {}, items })
const list = (items: any[]) => ({ id: 'l', type: 'list', data: { style: 'unordered', items } })
const heading = (text: string) => ({ id: 'h', type: 'header', data: { text, level: 3 }, tunes: { anchor: 'kept-title', textAlign: 'center' } })
const quote = (text: string, caption: string) => ({ id: 'q', type: 'quote', data: { text, caption, alignment: 'left' } })
type Endpoint = { selector: string; marker: string; nth?: number }
const endpoint = (selector: string, marker: string, nth = 0): Endpoint => ({ selector, marker, nth })

async function textPoint(page: Page, endpoint: Endpoint): Promise<{ x: number; y: number }> {
  const field = page.locator(endpoint.selector).nth(endpoint.nth ?? 0)
  await field.scrollIntoViewIfNeeded()
  return field.evaluate((field, marker) => {
    let offset = field.textContent!.indexOf(marker)
    if (offset < 0) throw new Error(`Missing selection marker: ${marker}`)
    const walker = document.createTreeWalker(field, NodeFilter.SHOW_TEXT)
    while (walker.nextNode()) {
      if (offset < walker.currentNode.textContent!.length) break
      offset -= walker.currentNode.textContent!.length
    }
    const range = document.createRange()
    range.setStart(walker.currentNode, offset)
    range.setEnd(walker.currentNode, offset + 1)
    const rect = range.getBoundingClientRect()
    return { x: rect.x + 0.25, y: rect.y + rect.height / 2 }
  }, endpoint.marker)
}

async function drag(page: Page, first: Endpoint, last: Endpoint, backward = false): Promise<void> {
  const points = [await textPoint(page, first), await textPoint(page, last)]
  if (backward) points.reverse()
  await page.mouse.move(points[0]!.x, points[0]!.y)
  await page.mouse.down()
  await page.mouse.move(points[1]!.x, points[1]!.y, { steps: 30 })
  await page.mouse.up()
  await expect(page.locator('.ce-block--selected')).toHaveCount(0)
  await expect.poll(() => page.evaluate(() => CSS.highlights.has('pw-text-selection'))).toBe(true)
}

async function fields(page: Page): Promise<string[]> {
  return page.locator('.ce-block__content [contenteditable="true"]').evaluateAll(fields =>
    fields.filter(field => !field.closest('.editorjs-monaco-wrapper')).map(field => field.textContent ?? ''),
  )
}

async function data(page: Page): Promise<any[]> {
  return page.evaluate(async () => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
    return (await window.editors[holder.id].save()).blocks.map(({ type, data, tunes }: any) => ({ type, data, tunes }))
  })
}

const cases = [
  { name: 'paragraph to list', blocks: [paragraph('p', 'BEFORE|CUT'), list([item('CUT|AFTER'), item('OTHER')])],
    from: endpoint('[data-id=p] .ce-paragraph', 'CUT'), to: endpoint('.cdx-list__item-content', 'AFTER'),
    expected: ['BEFORE|', 'AFTER', 'OTHER'], types: ['paragraph', 'list'] },
  { name: 'list to paragraph', blocks: [list([item('BEFORE|CUT'), item('SELECTED')]), paragraph('p', 'CUT|AFTER')],
    from: endpoint('.cdx-list__item-content', 'CUT'), to: endpoint('.ce-paragraph', 'AFTER'),
    expected: ['BEFORE|', 'AFTER'], types: ['list', 'paragraph'] },
  { name: 'paragraph to heading', blocks: [paragraph('p', 'BEFORE|CUT'), heading('CUT|AFTER')],
    from: endpoint('.ce-paragraph', 'CUT'), to: endpoint('.ce-header', 'AFTER'),
    expected: ['BEFORE|', 'AFTER'], types: ['paragraph', 'header'] },
  { name: 'heading to paragraph', blocks: [heading('BEFORE|CUT'), paragraph('p', 'CUT|AFTER')],
    from: endpoint('.ce-header', 'CUT'), to: endpoint('.ce-paragraph', 'AFTER'),
    expected: ['BEFORE|', 'AFTER'], types: ['header', 'paragraph'] },
  { name: 'paragraph to a nested child', blocks: [paragraph('p', 'BEFORE|CUT'), list([item('SELECTED', [item('CUT|AFTER'), item('OTHER CHILD')]), item('OTHER')])],
    from: endpoint('.ce-paragraph', 'CUT'), to: endpoint('.cdx-list__item-content', 'AFTER', 1),
    expected: ['BEFORE|', '', 'AFTER', 'OTHER CHILD', 'OTHER'], types: ['paragraph', 'list'] },
  { name: 'two sibling list items', blocks: [list([item('BEFORE|CUT'), item('CUT|AFTER', [item('CHILD')]), item('OTHER')])],
    from: endpoint('.cdx-list__item-content', 'CUT'), to: endpoint('.cdx-list__item-content', 'AFTER', 1),
    expected: ['BEFORE|AFTER', 'CHILD', 'OTHER'], types: ['list'] },
  { name: 'parent to nested child', blocks: [list([item('BEFORE|CUT', [item('CUT|AFTER', [item('GRANDCHILD')])]), item('OTHER')])],
    from: endpoint('.cdx-list__item-content', 'CUT'), to: endpoint('.cdx-list__item-content', 'AFTER', 1),
    expected: ['BEFORE|', 'AFTER', 'GRANDCHILD', 'OTHER'], types: ['list'] },
  { name: 'paragraph to quote body', blocks: [paragraph('p', 'BEFORE|CUT'), quote('CUT|AFTER', 'CAPTION')],
    from: endpoint('.ce-paragraph', 'CUT'), to: endpoint('.cdx-quote__text', 'AFTER'),
    expected: ['BEFORE|', 'AFTER', 'CAPTION'], types: ['paragraph', 'quote'] },
  { name: 'quote caption to paragraph', blocks: [quote('BODY', 'BEFORE|CUT'), paragraph('p', 'CUT|AFTER')],
    from: endpoint('.cdx-quote__caption', 'CUT'), to: endpoint('.ce-paragraph', 'AFTER'),
    expected: ['BODY', 'BEFORE|', 'AFTER'], types: ['quote', 'paragraph'] },
  { name: 'paragraph to quote caption', blocks: [paragraph('p', 'BEFORE|CUT'), quote('SELECTED BODY', 'CUT|AFTER')],
    from: endpoint('.ce-paragraph', 'CUT'), to: endpoint('.cdx-quote__caption', 'AFTER'),
    expected: ['BEFORE|', '', 'AFTER'], types: ['paragraph', 'quote'] },
  { name: 'paragraph to table cell', blocks: [paragraph('p', 'BEFORE|CUT'), { id: 't', type: 'table', data: { withHeadings: true, content: [['CUT|AFTER', 'OTHER'], ['ROW', 'LAST']] } }],
    from: endpoint('.ce-paragraph', 'CUT'), to: endpoint('.tc-cell', 'AFTER'),
    expected: ['BEFORE|', 'AFTER', 'OTHER', 'ROW', 'LAST'], types: ['paragraph', 'table'] },
  { name: 'across table cells', blocks: [{ id: 't', type: 'table', data: { content: [['BEFORE|CUT', 'SELECTED'], ['CUT|AFTER', 'OTHER']] } }],
    from: endpoint('.tc-cell', 'CUT'), to: endpoint('.tc-cell', 'AFTER', 2),
    expected: ['BEFORE|', '', 'AFTER', 'OTHER'], types: ['table'] },
  { name: 'paragraph separator paragraph', blocks: [paragraph('p', 'BEFORE|CUT'), { id: 'd', type: 'delimiter', data: {} }, paragraph('z', 'CUT|AFTER')],
    from: endpoint('[data-id=p] .ce-paragraph', 'CUT'), to: endpoint('[data-id=z] .ce-paragraph', 'AFTER'),
    expected: ['BEFORE|AFTER'], types: ['paragraph'] },
  { name: 'paragraph paragraph list', blocks: [paragraph('p', 'BEFORE|CUT'), paragraph('m', 'SELECTED'), list([item('CUT|AFTER'), item('OTHER')])],
    from: endpoint('[data-id=p] .ce-paragraph', 'CUT'), to: endpoint('.cdx-list__item-content', 'AFTER'),
    expected: ['BEFORE|', 'AFTER', 'OTHER'], types: ['paragraph', 'list'] },
]

for (const scenario of cases) {
  for (const backward of [false, true]) {
    const key = backward ? 'Delete' : 'Backspace'
    test(`${scenario.name}: ${key} preserves unselected content and structure when dragging ${backward ? 'backward' : 'forward'}`, async ({ editorPage: page }) => {
      await render(page, scenario.blocks)
      const before = await data(page)
      await drag(page, scenario.from, scenario.to, backward)
      await page.keyboard.press(key)
      await expect.poll(() => fields(page)).toEqual(scenario.expected)
      const after = await data(page)
      expect(after.map(block => block.type)).toEqual(scenario.types)
      await page.keyboard.press('Control+z')
      await expect.poll(() => data(page)).toEqual(before)
      await page.keyboard.press('Control+y')
      await expect.poll(() => data(page)).toEqual(after)
      await saveAndReload(page)
      expect(await fields(page)).toEqual(scenario.expected)
      if (scenario.types.includes('header')) {
        await expect(page.locator('h3.ce-header')).toBeVisible()
        expect((await data(page)).find(block => block.type === 'header').tunes).toEqual({ anchor: 'kept-title', textAlign: 'center' })
      }
    })
  }
}

test('native Unicode input over a mixed range keeps its boundary tools', async ({ editorPage: page }) => {
  const scenario = cases[0]!
  await render(page, scenario.blocks)
  await drag(page, scenario.from, scenario.to)
  await page.keyboard.insertText('été')
  await expect.poll(() => fields(page)).toEqual(['BEFORE|été', 'AFTER', 'OTHER'])
  await page.keyboard.press('Control+z')
  await expect.poll(() => fields(page)).toEqual(['BEFORE|CUT', 'CUT|AFTER', 'OTHER'])
})

test('Enter replaces a mixed range with a paragraph break and undoes once', async ({ editorPage: page }) => {
  const scenario = cases[0]!
  await render(page, scenario.blocks)
  const before = await data(page)
  await drag(page, scenario.from, scenario.to)
  await page.keyboard.press('Enter')
  await expect.poll(() => fields(page)).toEqual(['BEFORE|', '', 'AFTER', 'OTHER'])
  await expect(page.locator('.ce-paragraph')).toHaveCount(2)
  await expect(page.locator('.cdx-list')).toHaveCount(1)
  await page.keyboard.press('Control+z')
  await expect.poll(() => data(page)).toEqual(before)
})

test('pasting over a mixed range keeps the list suffix and undoes in one step', async ({ editorPage: page }) => {
  await render(page, [paragraph('p', 'SOURCE BEFORE|CUT'), cases[0]!.blocks[1]!])
  await page.locator('.ce-paragraph').click()
  await page.keyboard.press('Home')
  for (let i = 0; i < 6; i++) await page.keyboard.press('Shift+ArrowRight')
  await page.keyboard.press('Control+c')
  await drag(page, endpoint('.ce-paragraph', 'CUT'), endpoint('.cdx-list__item-content', 'AFTER'))
  await page.keyboard.press('Control+v')
  await expect.poll(() => fields(page)).toEqual(['SOURCE BEFORE|SOURCE', 'AFTER', 'OTHER'])
  await page.keyboard.press('Control+z')
  await expect.poll(() => fields(page)).toEqual(['SOURCE BEFORE|CUT', 'CUT|AFTER', 'OTHER'])
})

for (const shortcut of ['Control+x', 'Shift+Delete']) {
  test(`${shortcut} cuts only the mixed text range and undoes once`, async ({ editorPage: page }) => {
    const scenario = cases[0]!
    await render(page, scenario.blocks)
    await drag(page, scenario.from, scenario.to)
    await page.keyboard.press(shortcut)
    await expect.poll(() => fields(page)).toEqual(scenario.expected)
    await page.evaluate(() => document.body.append(Object.assign(document.createElement('textarea'), { id: 'clipboard-target' })))
    await page.locator('#clipboard-target').focus()
    await page.keyboard.press('Control+v')
    await expect(page.locator('#clipboard-target')).toHaveValue('CUT\n\nCUT|')
    await page.locator('.ce-paragraph').focus()
    await page.keyboard.press('Control+z')
    await expect.poll(() => fields(page)).toEqual(['BEFORE|CUT', 'CUT|AFTER', 'OTHER'])
  })
}

test('Shift+click keeps precise endpoints across a paragraph and a list', async ({ editorPage: page }) => {
  const scenario = cases[0]!
  await render(page, scenario.blocks)
  const start = await textPoint(page, scenario.from)
  const end = await textPoint(page, scenario.to)
  await page.mouse.click(start.x, start.y)
  await page.keyboard.down('Shift')
  await page.mouse.click(end.x, end.y)
  await page.keyboard.up('Shift')
  await page.keyboard.press('Backspace')
  await expect.poll(() => fields(page)).toEqual(scenario.expected)
})

test('Shift+ArrowRight crosses editing hosts without selecting whole blocks', async ({ editorPage: page }) => {
  await render(page, [paragraph('p', 'BEFORE|CUT'), heading('DROP|AFTER')])
  const start = await textPoint(page, endpoint('.ce-paragraph', 'CUT'))
  await page.mouse.click(start.x, start.y)
  for (let i = 0; i < 9; i++) await page.keyboard.press('Shift+ArrowRight')
  await expect(page.locator('.ce-block--selected')).toHaveCount(0)
  await page.keyboard.press('Backspace')
  await expect.poll(() => fields(page)).toEqual(['BEFORE|', 'AFTER'])
})

const groupStart = { id: 'gs', type: 'groupStart', data: { anchor: 'kept-group', class: 'grid', collapsible: false, legacy: false } }
const groupEnd = { id: 'ge', type: 'groupEnd', data: { collapsible: false, legacy: false, args: '' } }

test('crossing one group boundary keeps its pair and unselected grouped content', async ({ editorPage: page }) => {
  await render(page, [paragraph('p', 'BEFORE|CUT'), groupStart, paragraph('z', 'CUT|AFTER'), paragraph('kept', 'KEPT IN GROUP'), groupEnd])
  await drag(page, endpoint('[data-id=p] .ce-paragraph', 'CUT'), endpoint('[data-id=z] .ce-paragraph', 'AFTER'))
  await page.keyboard.press('Backspace')
  expect((await data(page)).map(block => block.type)).toEqual(['paragraph', 'groupStart', 'paragraph', 'paragraph', 'groupEnd'])
  await saveAndReload(page)
  expect((await data(page)).map(block => block.type)).toEqual(['paragraph', 'groupStart', 'paragraph', 'paragraph', 'groupEnd'])
  expect(await fields(page)).toEqual(['BEFORE|', 'AFTER', 'KEPT IN GROUP'])
})

test('a fully covered group is removed with both markers in one undoable operation', async ({ editorPage: page }) => {
  await render(page, [paragraph('p', 'BEFORE|CUT'), groupStart, paragraph('inside', 'SELECTED'), groupEnd, paragraph('z', 'CUT|AFTER')])
  const before = await data(page)
  await drag(page, endpoint('[data-id=p] .ce-paragraph', 'CUT'), endpoint('[data-id=z] .ce-paragraph', 'AFTER'))
  await page.keyboard.press('Backspace')
  await expect.poll(() => fields(page)).toEqual(['BEFORE|AFTER'])
  expect((await data(page)).map(block => block.type)).toEqual(['paragraph'])
  await page.keyboard.press('Control+z')
  await expect.poll(() => data(page)).toEqual(before)
})

for (const type of ['raw', 'codeBlock']) {
  test(`a fully covered ${type} between text endpoints is atomic and undoable`, async ({ editorPage: page }) => {
    await render(page, [paragraph('p', 'BEFORE|CUT'), { id: 'code', type, data: { html: '<div>CODE</div>', language: 'html' } }, paragraph('z', 'CUT|AFTER')])
    const before = await data(page)
    await drag(page, endpoint('[data-id=p] .ce-paragraph', 'CUT'), endpoint('[data-id=z] .ce-paragraph', 'AFTER'))
    await expect(page.locator('[data-id=code]')).toHaveClass(/pw-text-selection-block/)
    await page.keyboard.press('Backspace')
    await expect.poll(() => fields(page)).toEqual(['BEFORE|AFTER'])
    expect((await data(page)).map(block => block.type)).toEqual(['paragraph'])
    await page.keyboard.press('Control+z')
    await expect.poll(() => data(page)).toEqual(before)
  })
}

test('a range ending on a code block keeps the preceding paragraph prefix', async ({ editorPage: page }) => {
  await render(page, [paragraph('p', 'BEFORE|CUT'), { id: 'code', type: 'raw', data: { html: '<div>CODE</div>' } }])
  await expect(page.locator('[data-id=code] .monaco-editor')).toBeVisible()
  const before = await data(page)
  const start = await textPoint(page, endpoint('.ce-paragraph', 'CUT'))
  const end = (await page.locator('[data-id=code] .editorjs-monaco-wrapper').boundingBox())!
  await page.mouse.move(start.x, start.y)
  await page.mouse.down()
  await page.mouse.move(end.x + end.width / 2, end.y + end.height / 2, { steps: 30 })
  await page.mouse.up()
  await page.keyboard.press('Backspace')
  await expect(page.locator('.ce-paragraph')).toHaveText('BEFORE|')
  await expect(page.locator('[data-id=code] .editorjs-monaco-wrapper')).toHaveCount(0)
  await page.keyboard.press('Control+z')
  await expect.poll(() => data(page)).toEqual(before)
  // The restored atomic endpoint must remain editable as a selection.
  await page.keyboard.press('Delete')
  await expect(page.locator('[data-id=code] .editorjs-monaco-wrapper')).toHaveCount(0)
  await expect(page.locator('.ce-paragraph')).toHaveText('BEFORE|')
})

test('a range starting on a code block keeps the following paragraph suffix through undo', async ({ editorPage: page }) => {
  await render(page, [{ id: 'code', type: 'raw', data: { html: '<div>CODE</div>' } }, paragraph('p', 'CUT|AFTER')])
  const line = page.locator('[data-id=code] .view-line').first()
  await expect(line).toBeVisible()
  const before = await data(page)
  const start = (await line.boundingBox())!
  const end = await textPoint(page, endpoint('.ce-paragraph', 'AFTER'))
  await page.mouse.move(start.x + 10, start.y + start.height / 2)
  await page.mouse.down()
  await page.mouse.move(end.x, end.y, { steps: 30 })
  await page.mouse.up()
  await page.keyboard.press('Backspace')
  await expect(page.locator('.ce-paragraph')).toHaveText('AFTER')
  await expect(page.locator('[data-id=code]')).toHaveCount(0)
  await page.keyboard.press('Control+z')
  await expect.poll(() => data(page)).toEqual(before)
  await page.keyboard.type('x')
  await expect(page.locator('.ce-paragraph')).toHaveText('xAFTER')
  await expect(page.locator('[data-id=code]')).toHaveCount(0)
})

test('native input supplied after a mixed selection restores the full selection through undo', async ({ editorPage: page }) => {
  await render(page, cases[0]!.blocks)
  await page.locator('.ce-paragraph').evaluate(field => {
    const end = document.querySelector('.cdx-list__item-content')!
    field.focus()
    getSelection()!.setBaseAndExtent(field.firstChild!, 7, end.firstChild!, 4)
  })
  await expect.poll(() => page.evaluate(() => CSS.highlights.has('pw-text-selection'))).toBe(true)
  await page.keyboard.press('Backspace')
  await expect.poll(() => fields(page)).toEqual(cases[0]!.expected)
  await page.keyboard.press('Control+z')
  await expect.poll(() => fields(page)).toEqual(['BEFORE|CUT', 'CUT|AFTER', 'OTHER'])
  await page.keyboard.type('x')
  await expect.poll(() => fields(page)).toEqual(['BEFORE|x', 'AFTER', 'OTHER'])
})

test('clearing an image caption keeps the image and an empty saved caption', async ({ editorPage: page }) => {
  await page.route('**/media/**', route => route.fulfill({
    contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="60"><rect width="80" height="60" fill="teal"/></svg>',
  }))
  await render(page, [{ id: 'image', type: 'image', data: { media: 'piedweb-logo.png', caption: 'CAPTION CUT' } }, paragraph('p', 'CUT|AFTER')])
  await expect(page.locator('.image-tool__caption')).toBeVisible()
  await drag(page, endpoint('.image-tool__caption', 'CAPTION CUT'), endpoint('.ce-paragraph', 'AFTER'))
  await page.keyboard.press('Backspace')
  await expect(page.locator('.image-tool__image-picture')).toBeVisible()
  const image = (await data(page)).find(block => block.type === 'image')
  expect(image.data).toEqual({ media: 'piedweb-logo.png', caption: '' })
  await saveAndReload(page)
  await expect(page.locator('.image-tool__image-picture')).toBeVisible()
  expect((await data(page)).find(block => block.type === 'image').data.caption).toBe('')
  await expect(page.locator('.ce-paragraph')).toHaveText('AFTER')
})

test('Shift+ArrowDown extends across a heading without replacing whole blocks', async ({ editorPage: page }) => {
  await render(page, [paragraph('p', 'BEFORE|CUT'), heading('DROP '.repeat(20) + 'AFTER')])
  const start = await textPoint(page, endpoint('.ce-paragraph', 'CUT'))
  await page.mouse.click(start.x, start.y)
  await page.keyboard.press('Shift+ArrowDown')
  await expect(page.locator('.ce-block--selected')).toHaveCount(0)
  await expect.poll(() => page.evaluate(() => CSS.highlights.has('pw-text-selection'))).toBe(true)
  await page.keyboard.press('Backspace')
  await expect(page.locator('.ce-paragraph')).toHaveText('BEFORE|')
  await expect(page.locator('.ce-header')).toContainText('AFTER')
})
