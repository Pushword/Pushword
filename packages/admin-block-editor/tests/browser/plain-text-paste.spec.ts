import { test, expect, render, saveAndReload } from './fixtures'
import type { Page } from '@playwright/test'

const text = '- Une agence au service des guides et des voyageurs'

async function copySource(page: Page, richText = false): Promise<void> {
  // Copy from another document so Pushword's Markdown copy handler cannot alter the source.
  const source = await page.context().newPage()
  await source.setContent(`<div contenteditable="true">${richText ? `<ul><li><b>${text}</b></li></ul>` : text}</div>`)
  await source.locator('[contenteditable]').focus()
  await source.keyboard.press('Control+a')
  await source.keyboard.press('Control+c')
  await source.close()
  await page.bringToFront()
}

for (const richText of [false, true]) {
  test(`Ctrl+Shift+V keeps ${richText ? 'HTML' : 'plain text'} list syntax inside a heading`, async ({ editorPage: page }) => {
    await render(page, [{ type: 'header', data: { text: 'Before replace after', level: 2 } }])
    await copySource(page, richText)

    const heading = page.locator('h2.ce-header')
    await heading.click()
    await page.keyboard.press('Home')
    for (let i = 0; i < 'Before '.length; i++) await page.keyboard.press('ArrowRight')
    for (let i = 0; i < 'replace'.length; i++) await page.keyboard.press('Shift+ArrowRight')
    await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('replace')
    await page.keyboard.press('Control+Shift+v')

    await expect(heading).toHaveText(`Before ${text} after`)
    await expect(heading.locator('b, ul, li')).toHaveCount(0)
    await expect(page.locator('.ce-block')).toHaveCount(1)
    await page.keyboard.type('!')
    await expect(heading).toHaveText(`Before ${text}! after`)
    await page.keyboard.press('Control+z')
    await page.keyboard.press('Control+z')
    await expect(heading).toHaveText('Before replace after')
    await page.keyboard.press('Control+Shift+z')
    await expect(heading).toHaveText(`Before ${text} after`)
    await saveAndReload(page)
    await expect(page.locator('h2.ce-header')).toHaveText(`Before ${text} after`)
    await expect(page.locator('.ce-block')).toHaveCount(1)
  })
}

test('normal Ctrl+V still imports a Markdown list after a plain text paste', async ({ editorPage: page }) => {
  await render(page, [{ type: 'header', data: { text: 'Heading', level: 2 } }])
  await copySource(page)
  const heading = page.locator('h2.ce-header')
  await heading.click()
  await page.keyboard.press('End')
  await page.keyboard.press('Control+Shift+v')
  await expect(heading).toHaveText(`Heading${text}`)
  await page.keyboard.press('Control+v')
  await expect(page.locator('.cdx-list__item-content')).toHaveText(text.slice(2))
  await expect(page.locator('.ce-block')).toHaveCount(2)
  await expect(heading).toHaveText(`Heading${text}`)
})
