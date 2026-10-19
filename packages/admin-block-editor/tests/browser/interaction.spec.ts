import { readFileSync } from 'node:fs'
import { test, expect, render, saveAndReload } from './fixtures'
import type { Page } from '@playwright/test'

if (process.env.PW_EDITOR_WINDOWS) {
  for (const suggestions of ['default', 'disabled']) {
    test(`Windows French AZERTY physical keys and dead keys survive saving, suggestions ${suggestions}`, async ({
      editorPage: page,
      request,
    }) => {
      await render(page, [{ type: 'paragraph', data: { text: '' } }])
      if (suggestions === 'disabled') {
        await page.locator('[data-pw-diagnostics-for] summary').click()
        await page.locator('[data-pw-writing-suggestions]').check()
      }
      await page.bringToFront()
      await page.locator('.ce-paragraph').click()
      const response = await request.post('http://127.0.0.1:19881/french-keyboard', {
        timeout: 45_000,
      })
      expect(response.ok(), await response.text()).toBe(true)
      await expect(page.locator('.ce-paragraph')).toHaveText('é ê ü')
      await saveAndReload(page)
      await expect(page.locator('.ce-paragraph')).toHaveText('é ê ü')
    })
  }
}

async function pasteHtml(page: Page, html: string, text: string): Promise<void> {
  const source = await page.context().newPage()
  await source.goto('/login')
  await source.setContent('<button type="button">Copy document</button>')
  await source.evaluate(
    ({ html, text }) => {
      document.querySelector('button')!.addEventListener('click', async () => {
        await navigator.clipboard.write([
          new ClipboardItem({
            'text/html': new Blob([html], { type: 'text/html' }),
            'text/plain': new Blob([text], { type: 'text/plain' }),
          }),
        ])
        document.body.dataset.copied = '1'
      })
    },
    { html, text },
  )
  await source.locator('button').click()
  await expect(source.locator('body')).toHaveAttribute('data-copied', '1')
  await source.close()
  await page.bringToFront()
  await page.locator('.ce-paragraph').first().click()
  await page.keyboard.press('End')
  await page.keyboard.press('Control+v')
}

test('French accented text, selection replacement and deletion survive undo and saving', async ({
  editorPage: page,
}) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Texte initial' } }])
  const field = page.locator('.ce-paragraph')
  await field.click()
  await page.keyboard.press('Home')
  await page.keyboard.press('Shift+End')
  // One replacement operation; keyboard.type mixes composition and key events in Firefox.
  await page.keyboard.insertText('Séjour à Noël, été, cœur, forêt.')
  await expect(field).toHaveText('Séjour à Noël, été, cœur, forêt.')
  await page.keyboard.press('Control+z')
  await expect(field).toHaveText('Texte initial')
  await page.keyboard.press('Control+y')
  await expect(field).toHaveText('Séjour à Noël, été, cœur, forêt.')
  await page.keyboard.press('End')
  await page.keyboard.press('Backspace')
  await saveAndReload(page)
  await expect(field).toHaveText('Séjour à Noël, été, cœur, forêt')
})

test('typing over selected blocks inserts one character', async ({
  editorPage: page,
}, testInfo) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'First paragraph' } },
    { type: 'paragraph', data: { text: 'Second paragraph' } },
  ])
  const fields = page.locator('.ce-paragraph')
  await fields.first().click()
  // A single-input block first selects its text, then all editor blocks.
  await page.keyboard.press('Control+a')
  await page.keyboard.press('Control+a')
  await testInfo.attach('selection-before-replacement', {
    contentType: 'application/json',
    body: JSON.stringify(
      await page.evaluate(() => ({
        text: getSelection()?.toString(),
        selectedBlocks: document.querySelectorAll('.ce-block--selected').length,
        active: document.activeElement?.className,
      })),
    ),
  })
  await expect(page.locator('.ce-block--selected')).toHaveCount(2)
  await page.keyboard.press('x')
  await expect(fields).toHaveCount(1)
  await expect(fields).toHaveText('x')
  await page.keyboard.press('Control+z')
  await expect(fields).toHaveText(['First paragraph', 'Second paragraph'])
  await page.keyboard.press('Control+y')
  await expect(fields).toHaveText('x')
  await saveAndReload(page)
  await expect(fields).toHaveText('x')
})

test('Office HTML paragraphs and table paste through the system clipboard and survive saving', async ({
  editorPage: page,
}) => {
  await render(page, [{ type: 'paragraph', data: { text: '' } }])
  await pasteHtml(
    page,
    '<html xmlns:o="urn:schemas-microsoft-com:office:office"><body><!--StartFragment-->' +
      '<p class="MsoNormal"><b>Voyage été</b></p>' +
      '<table style="mso-table-lspace:0pt"><tr><td>Destination</td><td>Prix</td></tr><tr><td>Alpes</td><td>120 €</td></tr></table>' +
      '<!--EndFragment--></body></html>',
    'Voyage été\nDestination\tPrix\nAlpes\t120 €',
  )
  await expect(page.locator('.ce-paragraph b, .ce-paragraph strong')).toHaveText(
    'Voyage été',
  )
  await expect(page.locator('.tc-cell')).toHaveText([
    'Destination',
    'Prix',
    'Alpes',
    '120 €',
  ])
  await page.keyboard.press('Control+z')
  await expect(page.locator('.tc-cell')).toHaveCount(0)
  await page.keyboard.press('Control+y')
  await expect(page.locator('.tc-cell')).toHaveCount(4)
  await saveAndReload(page)
  await expect(page.locator('.tc-cell')).toHaveText([
    'Destination',
    'Prix',
    'Alpes',
    '120 €',
  ])
})

test('rich-text nested lists keep their hierarchy through native paste and saving', async ({
  editorPage: page,
}) => {
  await render(page, [{ type: 'paragraph', data: { text: '' } }])
  await pasteHtml(
    page,
    '<ul><li>Parent<ul><li><b>Child</b><ul><li>Grandchild</li></ul></li></ul></li><li>Sibling</li></ul>',
    'Parent\nChild\nGrandchild\nSibling',
  )
  const child = page.locator('.cdx-list__item-children .cdx-list__item-content').first()
  const grandchild = page.locator(
    '.cdx-list__item-children .cdx-list__item-children .cdx-list__item-content',
  )
  await expect(child).toHaveText('Child')
  await expect(grandchild).toHaveText('Grandchild')
  await saveAndReload(page)
  await expect(child).toHaveText('Child')
  await expect(grandchild).toHaveText('Grandchild')
})

test('diagnostic download excludes page text and includes the suggestions experiment', async ({
  editorPage: page,
}, testInfo) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Private author content' } }])
  await page.evaluate(() => window.dispatchEvent(new ErrorEvent('error', {
    message: 'ResizeObserver loop completed with undelivered notifications.',
  })))
  await expect(page.locator('[data-pw-editor-error]')).toBeHidden()
  await page.locator('[data-pw-diagnostics-for] summary').click()
  await page.locator('[data-pw-writing-suggestions]').check()
  await expect(page.locator('.editorjs-holder')).toHaveAttribute(
    'writingsuggestions',
    'false',
  )
  await page.locator('.ce-paragraph').click()
  await page.keyboard.press('End')
  await page.keyboard.type(' secret')
  await page.evaluate(() => {
    document.querySelector('.editorjs-holder')!.dispatchEvent(
      new CustomEvent('pw:editor-error', {
        detail: {
          operation: 'copy',
          error: new DOMException('Private error text', 'NotAllowedError'),
        },
      }),
    )
  })
  await expect(page.locator('[data-pw-editor-error]')).toBeVisible()
  const downloaded = page.waitForEvent('download')
  await page.locator('[data-pw-download-diagnostic]').click()
  const download = await downloaded
  expect(download.suggestedFilename()).toBe('pushword-editor-diagnostic.json')
  const path = testInfo.outputPath('diagnostic.json')
  await download.saveAs(path)
  const reportText = readFileSync(path, 'utf8')
  const report = JSON.parse(reportText)
  expect(report.editorjs).toMatch(/^\d+\.\d+/)
  expect(report.environment.writingSuggestions).toBe('false')
  expect(report.errors).toContainEqual(
    expect.objectContaining({ operation: 'uncaught', warning: 'resize-observer-loop' }),
  )
  expect(report.errors).toContainEqual(
    expect.objectContaining({ operation: 'copy', name: 'NotAllowedError' }),
  )
  for (const secret of ['Private', 'author content', 'secret'])
    expect(reportText).not.toContain(secret)
  await page.locator('[data-pw-writing-suggestions]').uncheck()
  await expect(page.locator('.editorjs-holder')).toHaveAttribute(
    'writingsuggestions',
    'true',
  )
})
