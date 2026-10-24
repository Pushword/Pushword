import type { Page } from '@playwright/test'
import { test, expect, render } from './fixtures'

async function selectBlocks(page: Page, restored: boolean): Promise<void> {
  await render(page, [
    { id: 'a', type: 'paragraph', data: { text: 'First paragraph' } },
    { id: 'b', type: 'paragraph', data: { text: 'Second paragraph' } },
  ])
  await page.locator('.ce-paragraph').first().click()
  await page.keyboard.press('Control+a')
  await page.keyboard.press('Control+a')
  await expect(page.locator('.ce-block--selected')).toHaveCount(2)
  if (restored) {
    await page.keyboard.press('x')
    await expect(page.locator('.ce-paragraph')).toHaveText('x')
    await page.keyboard.press('Control+z')
    await expect(page.locator('.ce-block--selected')).toHaveCount(2)
    await expect(page.locator('.ce-paragraph').first()).toBeFocused()
  }
}

for (const restored of [false, true]) {
  const selection = restored ? 'restored' : 'native'
  test(`${selection} block selection: native Unicode input replaces blocks and stays undoable`, async ({ editorPage: page }) => {
    await selectBlocks(page, restored)
    await page.keyboard.insertText('ê')
    await expect(page.locator('.ce-paragraph')).toHaveText('ê')
    await expect(page.locator('.ce-block--selected')).toHaveCount(0)
    await page.keyboard.press('Control+z')
    await expect(page.locator('.ce-paragraph')).toHaveText(['First paragraph', 'Second paragraph'])
    await expect(page.locator('.ce-block--selected')).toHaveCount(2)
    await page.keyboard.press('Control+y')
    await expect(page.locator('.ce-paragraph')).toHaveText('ê')
    await page.keyboard.press('Backspace')
    await expect(page.locator('.ce-paragraph')).toHaveText('')
    await expect(page.locator('.ce-block')).toHaveCount(1)
  })

  for (const shortcut of ['Control+x', 'Shift+Delete']) {
    test(`${selection} block selection: ${shortcut} cuts to the native clipboard and undoes once`, async ({ editorPage: page }) => {
      await selectBlocks(page, restored)
      await page.keyboard.press(shortcut)
      await expect(page.locator('.ce-block')).toHaveCount(1)
      await expect(page.locator('.ce-paragraph')).toHaveText('')
      await page.evaluate(() => document.body.append(Object.assign(document.createElement('textarea'), { id: 'clipboard-target' })))
      await page.locator('#clipboard-target').click()
      await page.keyboard.press('Control+v')
      await expect(page.locator('#clipboard-target')).toHaveValue('First paragraph\n\nSecond paragraph')
      await page.locator('.ce-paragraph').click()
      await page.keyboard.press('Control+z')
      await expect(page.locator('.ce-paragraph')).toHaveText(['First paragraph', 'Second paragraph'])
      await expect(page.locator('.ce-block--selected')).toHaveCount(2)
    })
  }
}

test('composition commits one replacement and one history entry', async ({ editorPage: page }) => {
  await selectBlocks(page, true)
  await page.locator('.ce-paragraph').first().evaluate(field => {
    field.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true }))
    field.dispatchEvent(new InputEvent('beforeinput', { inputType: 'insertCompositionText', data: 'ê', isComposing: true, bubbles: true }))
    field.firstChild!.textContent = 'êFirst paragraph'
    field.dispatchEvent(new InputEvent('input', { inputType: 'insertCompositionText', data: 'ê', isComposing: true, bubbles: true }))
    field.dispatchEvent(new CompositionEvent('compositionend', { data: 'ê', bubbles: true }))
  })
  await expect(page.locator('.ce-paragraph')).toHaveText('ê')
  await page.keyboard.press('Control+z')
  await expect(page.locator('.ce-paragraph')).toHaveText(['First paragraph', 'Second paragraph'])
  await expect(page.locator('.ce-block--selected')).toHaveCount(2)
  await page.keyboard.press('Control+y')
  await expect(page.locator('.ce-paragraph')).toHaveText('ê')
})

test('native history discards a destroyed list item selection before the next keystroke', async ({ editorPage: page }) => {
  await render(page, [{ id: 'list', type: 'list', data: { style: 'unordered', items: [
    { content: 'First', meta: {}, items: [] }, { content: 'Second', meta: {}, items: [] }, { content: 'Third', meta: {}, items: [] },
  ] } }])
  const fields = page.locator('.cdx-list__item-content')
  await fields.first().click()
  await page.keyboard.press('End')
  await page.keyboard.type(' changed')
  await fields.nth(1).click({ modifiers: ['Shift'] })
  expect(await page.evaluate(() => CSS.highlights.get('pw-list-items')?.size)).toBe(2)
  await fields.first().evaluate(field => field.dispatchEvent(new InputEvent('beforeinput', { inputType: 'historyUndo', bubbles: true, cancelable: true })))
  await expect(fields.first()).toHaveText('First')
  await expect.poll(() => page.evaluate(() => CSS.highlights.has('pw-list-items'))).toBe(false)
  await page.keyboard.type('xy')
  await expect(fields).toHaveText(['Firstxy', 'Second', 'Third'])
})

test('native form fields restore the multi-block selection before their text range', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'quiz', data: { title: 'Original quiz', questions: [{ q: 'Question', answers: [{ a: 'Yes', correct: true }, { a: 'No' }] }] } },
    { type: 'paragraph', data: { text: 'Other block' } },
  ])
  await page.locator('.cdx-quiz__title').click()
  for (let count = 0; count < 3; count++) await page.keyboard.press('Control+a')
  await expect(page.locator('.ce-block--selected')).toHaveCount(2)
  await page.keyboard.press('x')
  await expect(page.locator('.ce-paragraph')).toHaveText('x')
  await page.keyboard.press('Control+z')
  await expect(page.locator('.cdx-quiz__title')).toHaveValue('Original quiz')
  await expect(page.locator('.ce-block--selected')).toHaveCount(2)
  await page.keyboard.press('x')
  await expect(page.locator('.ce-paragraph')).toHaveText('x')
  await expect(page.locator('.ce-block')).toHaveCount(1)
})

test('a normal text range still uses the browser cut shortcut', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Selected text' } }])
  await page.locator('.ce-paragraph').click()
  await page.keyboard.press('Home')
  await page.keyboard.press('Shift+End')
  await page.keyboard.press('Shift+Delete')
  await expect(page.locator('.ce-paragraph')).toHaveText('')
  await expect(page.locator('.ce-paragraph')).toBeFocused()
  await page.keyboard.press('Control+z')
  await expect(page.locator('.ce-paragraph')).toHaveText('Selected text')
})

test('cut blocks paste back with their tool data intact', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'quiz', data: { title: 'Original quiz', questions: [{ q: 'Question', answers: [{ a: 'Yes', correct: true }, { a: 'No' }] }] } },
    { type: 'paragraph', data: { text: 'Other block' } },
  ])
  await page.locator('.cdx-quiz__title').click()
  for (let count = 0; count < 3; count++) await page.keyboard.press('Control+a')
  await expect(page.locator('.ce-block--selected')).toHaveCount(2)
  await page.keyboard.press('Control+x')
  await expect(page.locator('.ce-block')).toHaveCount(1)
  await expect(page.locator('.ce-paragraph')).toHaveText('')
  await page.keyboard.press('Control+v')
  await expect(page.locator('.cdx-quiz__title')).toHaveValue('Original quiz')
  await expect(page.locator('.cdx-quiz__q-text')).toHaveValue('Question')
  await expect(page.locator('.ce-paragraph').filter({ hasText: 'Other block' })).toHaveText('Other block')
})
