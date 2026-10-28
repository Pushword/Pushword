import { test, expect, render, saveAndReload } from './fixtures'

for (const style of ['unordered', 'ordered', 'checklist']) {
  test(`${style}: Tab, Shift+Tab and nested content survive saving`, async ({ editorPage: page }) => {
    await render(page, [{ type: 'list', data: { style, items: [{ content: 'Parent', meta: {}, items: [] }] } }])
    const fields = page.locator('.cdx-list__item-content')
    await fields.first().click()
    await page.keyboard.press('End')
    await page.keyboard.press('Tab')
    await expect(fields.first()).toBeFocused()
    await page.keyboard.press('Enter')
    await page.keyboard.press('Tab')
    await page.keyboard.type('Child')
    const child = page.locator('.cdx-list__item-children .cdx-list__item-content').first()
    await expect(child).toHaveText('Child')
    await expect(child).toBeFocused()
    await page.keyboard.press('Shift+Tab')
    await expect(page.locator('.ce-block__content > .cdx-list > .cdx-list__item')).toHaveCount(2)
    await expect(page.locator('.cdx-list__item-children')).toHaveCount(0)
    await page.keyboard.press('Tab')
    await expect(child).toHaveText('Child')
    await page.keyboard.press('Enter')
    await page.keyboard.press('Tab')
    await page.keyboard.type('Grandchild')
    const grandchild = page.locator('.cdx-list__item-children .cdx-list__item-children .cdx-list__item-content')
    await expect(grandchild).toHaveText('Grandchild')
    await saveAndReload(page)
    await expect(page.locator('.ce-block__content > .cdx-list > .cdx-list__item')).toHaveCount(1)
    await expect(page.locator(`.ce-block__content > .cdx-list-${style}`)).toHaveCount(1)
    await expect(fields.first()).toHaveText('Parent')
    await expect(child).toHaveText('Child')
    await expect(grandchild).toHaveText('Grandchild')
  })
}

for (const [tool, selector] of [
  ['bold', 'b,strong'], ['italic', 'i,em'], ['inline-code', 'code'],
  ['marker', 'mark'], ['Strikethrough', 's,strike'], ['small', 'small'], ['link', 'a[href]'],
]) {
  test(`${tool}: native selection, toolbar, undo/redo and save`, async ({ editorPage: page }) => {
    await render(page, [{ type: 'paragraph', data: { text: 'Format me' } }])
    const field = page.locator('.ce-paragraph')
    await field.click()
    await page.keyboard.press('Home')
    await page.keyboard.down('Shift')
    for (let index = 0; index < 6; index++) await page.keyboard.press('ArrowRight')
    await page.keyboard.up('Shift')
    const button = page.locator(`.ce-inline-toolbar button[data-item-name="${tool}"], .ce-inline-toolbar [data-item-name="${tool}"] button`)
    await button.click()
    if (tool === 'link') {
      await page.locator('.link-options__url').fill('https://example.org/editor')
      await page.locator('.link-options__url').press('Enter')
    }
    await expect(field.locator(selector!)).toHaveText('Format')
    await page.keyboard.press('Control+z')
    await expect(field).toHaveJSProperty('innerHTML', 'Format me')
    await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('Format')
    await page.keyboard.press('Control+y')
    await expect(field.locator(selector!)).toHaveText('Format')
    await saveAndReload(page)
    await expect(field).toHaveText('Format me')
    await expect(field.locator(selector!)).toHaveText('Format')
    if (tool === 'link') await expect(field.locator('a')).toHaveAttribute('href', 'https://example.org/editor')
  })
}

test('native typing, line break, undo/redo and immediate save', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Original' } }])
  const field = page.locator('.ce-paragraph')
  await field.click()
  await page.keyboard.press('End')
  await page.keyboard.type(' first')
  // Two typing bursts, separated by the editor's history grouping window.
  await page.waitForTimeout(650)
  await page.keyboard.press('ArrowLeft')
  await page.keyboard.press('End')
  await page.keyboard.type(' second')
  await page.keyboard.press('Control+z')
  await expect(field).toHaveText('Original first')
  await page.keyboard.press('Control+Shift+z')
  await expect(field).toHaveText('Original first second')
  await page.keyboard.press('End')
  await page.keyboard.press('Shift+Enter')
  await page.keyboard.type('Next line')
  await saveAndReload(page)
  await expect(field).toHaveText('Original first secondNext line')
  await expect(field.locator('br')).toHaveCount(1)
})

test('table keyboard navigation and cell edits survive saving', async ({ editorPage: page }) => {
  await render(page, [{ type: 'table', data: { withHeadings: true, content: [['Name', 'Price'], ['Trip', '100']] } }])
  const cells = page.locator('.tc-cell')
  await cells.nth(0).click()
  await page.keyboard.press('Tab')
  await expect(cells.nth(1)).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(cells.nth(0)).toBeFocused()
  await page.keyboard.press('Enter')
  await expect(cells.nth(2)).toBeFocused()
  await cells.nth(2).fill('Alpine trip')
  await cells.nth(3).fill('120 €')
  await saveAndReload(page)
  await expect(cells).toHaveText(['Name', 'Price', 'Alpine trip', '120 €'])
})

test('native copy/paste preserves formatted text', async ({ editorPage: page }, testInfo) => {
  await render(page, [
    { type: 'paragraph', data: { text: '<b>Copy me</b>' } },
    { type: 'paragraph', data: { text: 'Destination:' } },
  ])
  const clipboard = page.evaluate(() => new Promise(resolve => {
    document.addEventListener('paste', event => resolve({
      text: event.clipboardData?.getData('text/plain'),
      html: event.clipboardData?.getData('text/html'),
    }), { capture: true, once: true })
  }))
  const fields = page.locator('.ce-paragraph')
  await fields.first().click()
  await page.keyboard.press('Home')
  await page.keyboard.press('Shift+End')
  await page.keyboard.press('Control+c')
  await fields.nth(1).click()
  await page.keyboard.press('End')
  await page.keyboard.type(' ')
  await page.keyboard.press('Control+v')
  await testInfo.attach('clipboard', { body: JSON.stringify(await clipboard), contentType: 'application/json' })
  await expect(fields.nth(1)).toHaveText('Destination: Copy me')
  await expect(fields.nth(1).locator('b,strong')).toHaveText('Copy me')
  await saveAndReload(page)
  await expect(fields.nth(1)).toHaveText('Destination: Copy me')
  await expect(fields.nth(1).locator('b,strong')).toHaveText('Copy me')
})

for (const mode of ['markdown', 'json']) {
  test(`${mode} source round trip preserves nested lists`, async ({ editorPage: page }) => {
    await render(page, [{ type: 'list', data: { style: 'ordered', items: [
      { content: 'Parent', meta: {}, items: [{ content: 'Child', meta: {}, items: [] }] },
    ] } }])
    const toggle = page.locator(`[data-pw-editor-mode="${mode}"]`)
    if (mode === 'markdown') await page.locator('.cdx-list__item-content').first().press('Control+u')
    else await toggle.click()
    await expect(page.locator('.editorjs-holder').locator('..').locator('.monaco-editor')).toBeVisible()
    if (mode === 'markdown') await toggle.press('Control+u')
    else await toggle.click()
    const child = page.locator('.cdx-list__item-children .cdx-list__item-content')
    await expect(child).toHaveText('Child')
    await saveAndReload(page)
    await expect(child).toHaveText('Child')
  })
}
