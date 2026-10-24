import type { OutputBlockData } from '@editorjs/editorjs'
import type { Page } from '@playwright/test'
import { test, expect, render, saveAndReload } from './fixtures'

async function restoreMovedBlockSelection(page: Page, block: OutputBlockData): Promise<void> {
  await render(page, [
    { id: 'before', type: 'paragraph', data: { text: 'Before the block' } },
    { ...block, id: 'subject' },
    { id: 'after', type: 'paragraph', data: { text: 'After the block' } },
  ])
  const subject = page.locator('.ce-block[data-id="subject"]')
  await subject.locator('[contenteditable="true"]').first().click()
  await subject.hover()
  await page.locator('.ce-toolbar__settings-btn').click()
  await page.locator('.ce-popover-item[data-item-name="move-up"]').click()
  await expect(page.locator('.ce-block').first()).toHaveAttribute('data-id', 'subject')
  await page.keyboard.press('Escape')
  await subject.locator('[contenteditable="true"]').first().click()
  await page.keyboard.press('Control+z')
  await expect(page.locator('.ce-block').nth(1)).toHaveAttribute('data-id', 'subject')
  await expect(subject).toBeFocused()
  await page.keyboard.press('Control+y')
  await expect(page.locator('.ce-block').first()).toHaveAttribute('data-id', 'subject')
  await expect(subject).toBeFocused()
  await expect(page.locator('.ce-block--selected')).toHaveCount(1)
}

for (const block of [
  { type: 'paragraph', data: { text: 'Paragraph text' } },
  { type: 'header', data: { text: 'Heading text', level: 2 } },
  { type: 'list', data: { style: 'unordered', items: [
    { content: 'First item', meta: {}, items: [] },
    { content: 'Second item', meta: {}, items: [] },
  ] } },
  { type: 'quote', data: { text: 'Quotation text', caption: 'Author', alignment: 'left' } },
  { type: 'table', data: { content: [['First cell', 'Second cell'], ['Third cell', 'Fourth cell']] } },
] satisfies OutputBlockData[]) {
  for (const key of ['Backspace', 'Delete', 'x']) {
    test(`${block.type}: clicking after undo/redo resumes character editing on ${key}`, async ({ editorPage: page }) => {
      await restoreMovedBlockSelection(page, block)
      const subject = page.locator('.ce-block[data-id="subject"]')
      const field = subject.locator('[contenteditable="true"]').first()
      const original = (await field.textContent())!
      let expected = `${original}x`
      if (key === 'Backspace') expected = original.slice(0, -1)
      else if (key === 'Delete') expected = original.slice(1)
      await field.click()
      await expect(page.locator('.ce-block--selected')).toHaveCount(0)
      await page.keyboard.press(key === 'Delete' ? 'Home' : 'End')
      await page.keyboard.press(key)
      await expect(page.locator('.ce-block')).toHaveCount(3)
      await expect(subject).toHaveCount(1)
      await expect(field).toHaveText(expected)
      await expect(page.locator('[data-id="before"] .ce-paragraph')).toHaveText('Before the block')
      await expect(page.locator('[data-id="after"] .ce-paragraph')).toHaveText('After the block')
    })
  }
}

test('clicking another block after undo/redo preserves the old block and saves only the character edit', async ({ editorPage: page }) => {
  await restoreMovedBlockSelection(page, { type: 'paragraph', data: { text: 'Original subject' } })
  const other = page.locator('[data-id="after"] .ce-paragraph')
  await other.click()
  await expect(page.locator('.ce-block--selected')).toHaveCount(0)
  await page.keyboard.press('End')
  await page.keyboard.press('Backspace')
  await expect(page.locator('[data-id="subject"] .ce-paragraph')).toHaveText('Original subject')
  await expect(other).toHaveText('After the bloc')
  await saveAndReload(page)
  await expect(page.locator('.ce-paragraph')).toHaveText(['Original subject', 'Before the block', 'After the bloc'])
})

for (const key of ['Escape', 'ArrowRight', 'Enter']) {
  test(`${key} ends a restored block selection`, async ({ editorPage: page }) => {
    await restoreMovedBlockSelection(page, { type: 'paragraph', data: { text: 'Original subject' } })
    await page.keyboard.press(key)
    await expect(page.locator('.ce-block--selected')).toHaveCount(0)
  })
}

for (const key of ['Backspace', 'Delete', 'x']) {
  test(`a restored multi-block selection can still be replaced directly with ${key}`, async ({ editorPage: page }) => {
    await render(page, [
      { type: 'paragraph', data: { text: 'First paragraph' } },
      { type: 'paragraph', data: { text: 'Second paragraph' } },
    ])
    await page.locator('.ce-paragraph').first().click()
    await page.keyboard.press('Control+a')
    await page.keyboard.press('Control+a')
    await expect(page.locator('.ce-block--selected')).toHaveCount(2)
    await page.keyboard.press('x')
    await expect(page.locator('.ce-paragraph')).toHaveText('x')
    await page.keyboard.press('Control+z')
    await expect(page.locator('.ce-block--selected')).toHaveCount(2)
    await expect(page.locator('.ce-block').first()).toBeFocused()
    await page.keyboard.press(key)
    await expect(page.locator('.ce-block')).toHaveCount(1)
    await expect(page.locator('.ce-paragraph')).toHaveText(key === 'x' ? 'x' : '')
  })
}

test('clicking after undoing a multi-block replacement resumes editing just one paragraph', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'First paragraph' } },
    { type: 'paragraph', data: { text: 'Second paragraph' } },
  ])
  await page.locator('.ce-paragraph').first().click()
  await page.keyboard.press('Control+a')
  await page.keyboard.press('Control+a')
  await expect(page.locator('.ce-block--selected')).toHaveCount(2)
  await page.keyboard.press('x')
  await expect(page.locator('.ce-paragraph')).toHaveText('x')
  await page.keyboard.press('Control+z')
  await expect(page.locator('.ce-block--selected')).toHaveCount(2)
  await expect(page.locator('.ce-block').first()).toBeFocused()
  await page.locator('.ce-paragraph').last().click()
  await expect(page.locator('.ce-block--selected')).toHaveCount(0)
  await page.keyboard.press('End')
  await page.keyboard.press('Backspace')
  await expect(page.locator('.ce-paragraph')).toHaveText(['First paragraph', 'Second paragrap'])
})

test('copying a word in another block after undo/redo copies only that word', async ({ editorPage: page }) => {
  await restoreMovedBlockSelection(page, { type: 'paragraph', data: { text: 'Original subject' } })
  const other = page.locator('[data-id="after"] .ce-paragraph')
  await other.click()
  await expect(page.locator('.ce-block--selected')).toHaveCount(0)
  const copied = await other.evaluate(field => {
    const selection = window.getSelection()!
    selection.setBaseAndExtent(field.firstChild!, 0, field.firstChild!, 5)
    const copy = new ClipboardEvent('copy', { clipboardData: new DataTransfer(), bubbles: true, cancelable: true })
    field.dispatchEvent(copy)
    return copy.clipboardData!.getData('text/plain')
  })
  expect(copied).toBe('After')
})
