import { test, expect, render, saveAndReload } from './fixtures'

for (const sample of [
  { name: 'plain text', text: "le voyage et la personne qui l'a imaginé", href: null },
  { name: 'an absolute URL', text: 'https://example.com/voyage', href: 'https://example.com/voyage' },
  { name: 'an internal URL', text: '/voyage', href: '/voyage' },
]) {
  test(`pasting ${sample.name} over selected text preserves the expected content`, async ({ editorPage: page }) => {
    await render(page, [{ type: 'paragraph', data: { text: 'mon mot' } }])
    await page.evaluate(text => {
      const source = document.createElement('textarea')
      source.id = 'clipboard-source'
      source.value = text
      document.body.append(source)
    }, sample.text)
    await page.locator('#clipboard-source').focus()
    await page.keyboard.press('Control+a')
    await page.keyboard.press('Control+c')
    await page.locator('#clipboard-source').evaluate(element => element.remove())

    const field = page.locator('.ce-paragraph')
    await field.click()
    await page.keyboard.press('Home')
    await page.keyboard.press('Shift+End')
    await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon mot')
    await page.keyboard.press('Control+v')

    const expectedText = sample.href ? 'mon mot' : sample.text
    await expect(field).toHaveText(expectedText)
    if (sample.href) {
      await expect(field.locator('a')).toHaveAttribute('href', sample.href)
    } else {
      await expect(field.locator('a')).toHaveCount(0)
    }
    await saveAndReload(page)
    await expect(field).toHaveText(expectedText)
    if (sample.href) {
      await expect(field.locator('a')).toHaveAttribute('href', sample.href)
    } else {
      await expect(field.locator('a')).toHaveCount(0)
    }
  })
}
