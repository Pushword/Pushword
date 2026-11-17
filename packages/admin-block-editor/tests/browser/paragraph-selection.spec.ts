import type { Page } from '@playwright/test'
import { test, expect, render, saveAndReload } from './fixtures'

const first = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.'
const removed = 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium '
const kept = 'doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.'

async function dragText(page: Page, start: number, end: number, backward = false): Promise<void> {
  const fields = page.locator('.ce-paragraph')
  await fields.first().scrollIntoViewIfNeeded()
  const points = await fields.evaluateAll((fields, offsets) => [fields[0], fields[fields.length - 1]].map((field, index) => {
    const walker = document.createTreeWalker(field, NodeFilter.SHOW_TEXT)
    let offset = offsets[index]
    while (walker.nextNode()) {
      if (offset < walker.currentNode.textContent!.length) break
      offset -= walker.currentNode.textContent!.length
    }
    const range = document.createRange()
    range.setStart(walker.currentNode, offset)
    range.setEnd(walker.currentNode, offset + 1)
    const rect = range.getBoundingClientRect()
    return { x: rect.x + 0.25, y: rect.y + rect.height / 2 }
  }), [start, end])
  if (backward) points.reverse()
  await page.mouse.move(points[0].x, points[0].y)
  await page.mouse.down()
  await page.mouse.move(points[1].x, points[1].y, { steps: 30 })
  await page.mouse.up()
  await expect(page.locator('.ce-block--selected')).toHaveCount(0)
  await expect.poll(() => page.evaluate(() => CSS.highlights.has('pw-text-selection'))).toBe(true)
}

for (const backward of [false, true]) {
  for (const key of ['Backspace', 'Delete']) {
    test(`${key} preserves the unselected paragraph suffix when dragging ${backward ? 'backward' : 'forward'}`, async ({ editorPage: page }) => {
      await render(page, [
        { type: 'paragraph', data: { text: first } },
        { type: 'paragraph', data: { text: removed + kept } },
      ])
      await dragText(page, 0, removed.length, backward)
      await page.keyboard.press(key)
      await expect(page.locator('.ce-paragraph')).toHaveText(kept)
      await page.keyboard.press('Control+z')
      await expect(page.locator('.ce-paragraph')).toHaveText([first, removed + kept])
      await page.keyboard.press('Control+y')
      await expect(page.locator('.ce-paragraph')).toHaveText(kept)
      await saveAndReload(page)
      await expect(page.locator('.ce-paragraph')).toHaveText(kept)
    })
  }
}

test('typing across three paragraphs preserves both formatted boundaries and places the caret between them', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: '<b>Before</b> remove this' }, tunes: { anchor: 'retained', textAlign: 'center' } },
    { type: 'paragraph', data: { text: 'Remove the middle paragraph' } },
    { type: 'paragraph', data: { text: 'Remove this <i>after</i>' } },
  ])
  await dragText(page, 6, 12)
  await page.keyboard.insertText(' été ')
  await expect(page.locator('.ce-paragraph')).toHaveText('Before été after')
  await expect(page.locator('.ce-paragraph b')).toHaveText('Before')
  await expect(page.locator('.ce-paragraph i')).toHaveText('after')
  await page.keyboard.type('!')
  await expect(page.locator('.ce-paragraph')).toHaveText('Before été !after')
  expect(await page.evaluate(async () => (await window.editors[document.querySelector<HTMLElement>('.editorjs-holder')!.id].save()).blocks[0].tunes))
    .toEqual({ anchor: 'retained', textAlign: 'center' })
  await saveAndReload(page)
  await expect(page.locator('.ce-paragraph')).toHaveText('Before été !after')
})

for (const shortcut of ['Control+x', 'Shift+Delete']) {
  test(`${shortcut} cuts only the selected text across paragraphs`, async ({ editorPage: page }) => {
    await render(page, [
      { type: 'paragraph', data: { text: 'Before selected' } },
      { type: 'paragraph', data: { text: 'also selected after' } },
    ])
    await dragText(page, 7, 13)
    await page.keyboard.press(shortcut)
    await expect(page.locator('.ce-paragraph')).toHaveText('Before  after')
    await page.evaluate(() => document.body.append(Object.assign(document.createElement('textarea'), { id: 'clipboard-target' })))
    await page.locator('#clipboard-target').focus()
    await page.keyboard.press('Control+v')
    await expect(page.locator('#clipboard-target')).toHaveValue('selected\n\nalso selected')
    await page.locator('.ce-paragraph').focus()
    await page.keyboard.press('Control+z')
    await expect(page.locator('.ce-paragraph')).toHaveText(['Before selected', 'also selected after'])
  })
}

test('clicking after a paragraph selection returns to ordinary text editing', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'First paragraph' } },
    { type: 'paragraph', data: { text: 'Second paragraph' } },
  ])
  await dragText(page, 0, 6)
  await page.locator('.ce-paragraph').last().click()
  await page.keyboard.press('End')
  await page.keyboard.type('!')
  await expect(page.locator('.ce-paragraph')).toHaveText(['First paragraph', 'Second paragraph!'])
  await expect.poll(() => page.evaluate(() => CSS.highlights.has('pw-text-selection'))).toBe(false)
})

test('dragging from a short paragraph reaches text beneath the inline toolbar', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'Lorem ipsum dolor sit amet.' } },
    { type: 'paragraph', data: { text: removed + kept } },
  ])
  await dragText(page, 0, removed.length)
  await page.keyboard.press('Backspace')
  await expect(page.locator('.ce-paragraph')).toHaveText(kept)
})

test('typing replaces the precise text range restored by undo', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'Before selected' } },
    { type: 'paragraph', data: { text: 'selected after' } },
  ])
  await dragText(page, 7, 8)
  await page.keyboard.press('Backspace')
  await expect(page.locator('.ce-paragraph')).toHaveText('Before  after')
  await page.keyboard.press('Control+z')
  await expect(page.locator('.ce-paragraph')).toHaveText(['Before selected', 'selected after'])
  await page.keyboard.type('x')
  await expect(page.locator('.ce-paragraph')).toHaveText('Before x after')
})

for (const inputType of ['deleteContentBackward', 'deleteContentForward']) {
  test(`native ${inputType} removes only the selected cross-paragraph text`, async ({ editorPage: page }) => {
    await render(page, [
      { type: 'paragraph', data: { text: 'Before selected' } },
      { type: 'paragraph', data: { text: 'selected after' } },
    ])
    await dragText(page, 7, 8)
    await page.locator('.ce-paragraph').first().evaluate((field, inputType) => {
      field.dispatchEvent(new InputEvent('beforeinput', { inputType, bubbles: true, cancelable: true }))
    }, inputType)
    await expect(page.locator('.ce-paragraph')).toHaveText('Before  after')
    await page.keyboard.press('Control+z')
    await expect(page.locator('.ce-paragraph')).toHaveText(['Before selected', 'selected after'])
  })
}

test('pasting over a cross-paragraph range preserves its unselected boundaries and undoes once', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'Kept selected' } },
    { type: 'paragraph', data: { text: 'selected tail' } },
  ])
  await page.locator('.ce-paragraph').first().click()
  await page.keyboard.press('Home')
  for (let index = 0; index < 4; index++) await page.keyboard.press('Shift+ArrowRight')
  await page.keyboard.press('Control+c')
  await dragText(page, 5, 8)
  await page.keyboard.press('Control+v')
  await expect(page.locator('.ce-paragraph')).toHaveText('Kept Kept tail')
  await page.keyboard.press('Control+z')
  await expect(page.locator('.ce-paragraph')).toHaveText(['Kept selected', 'selected tail'])
})

for (const key of ['ArrowLeft', 'ArrowRight', 'Escape']) {
  test(`${key} ends the paragraph range before ordinary typing`, async ({ editorPage: page }) => {
    await render(page, [
      { type: 'paragraph', data: { text: 'First paragraph' } },
      { type: 'paragraph', data: { text: 'Second paragraph' } },
    ])
    await dragText(page, 0, 6)
    await page.keyboard.press(key)
    await page.keyboard.type('!')
    await expect(page.locator('.ce-paragraph')).toHaveText(key === 'ArrowRight'
      ? ['First paragraph', 'Second! paragraph']
      : ['!First paragraph', 'Second paragraph'])
    await expect.poll(() => page.evaluate(() => CSS.highlights.has('pw-text-selection'))).toBe(false)
  })
}

test('copying a paragraph range keeps both paragraphs and copies only their selected text', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'Before selected' } },
    { type: 'paragraph', data: { text: 'selected after' } },
  ])
  await dragText(page, 7, 8)
  await page.keyboard.press('Control+c')
  await expect(page.locator('.ce-paragraph')).toHaveText(['Before selected', 'selected after'])
  await page.evaluate(() => document.body.append(Object.assign(document.createElement('textarea'), { id: 'clipboard-target' })))
  await page.locator('#clipboard-target').focus()
  await page.keyboard.press('Control+v')
  await expect(page.locator('#clipboard-target')).toHaveValue('selected\n\nselected')
})

test('Enter replaces a paragraph range with a paragraph break and undoes once', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'Before selected' } },
    { type: 'paragraph', data: { text: 'selected after' } },
  ])
  await dragText(page, 7, 8)
  await page.keyboard.press('Enter')
  await expect(page.locator('.ce-paragraph')).toHaveText(['Before ', ' after'])
  await page.keyboard.press('Control+z')
  await expect(page.locator('.ce-paragraph')).toHaveText(['Before selected', 'selected after'])
})
