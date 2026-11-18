import { test, expect, render, expectInsertionPreservesLayout } from './fixtures'
import type { Locator } from '@playwright/test'

test('failure recovery stays above long content with a tappable diagnostic download', async ({ editorPage: page }) => {
  for (const width of [390, 320]) {
    await page.setViewportSize({ width, height: 844 })
    await render(page, Array.from({ length: 30 }, (_, index) => ({
      type: 'paragraph', data: { text: `Paragraph ${index}` },
    })))
    await page.locator('.pw-editor-modes').scrollIntoViewIfNeeded()
    await page.locator('.editorjs-holder').evaluate(holder => holder.dispatchEvent(
      new CustomEvent('pw:editor-error', { detail: { operation: 'tool', error: new Error('Import failed') } }),
    ))

    const alert = page.locator('[data-pw-editor-error]')
    await expect(alert).toBeInViewport()
    const button = alert.getByRole('button', { name: 'Download diagnostic' })
    const bounds = (await button.boundingBox())!
    expect(bounds.height).toBeGreaterThanOrEqual(44)
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(width)
    const downloaded = page.waitForEvent('download')
    await button.tap()
    expect((await downloaded).suggestedFilename()).toBe('pushword-editor-diagnostic.json')
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width)
  }
})

test('the paragraph plus stays visible and tappable between non-text blocks', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'table', data: { content: [['Keep cell']] } },
    { type: 'raw', data: { html: '<div>Keep raw</div>' } },
  ])
  const button = page.getByRole('button', { name: 'Add paragraph', exact: true })
  await expect(page.locator('.pw-block-insertion')).toHaveCSS('opacity', '1')
  await expect(button).toHaveCSS('height', '44px')
  await expectInsertionPreservesLayout(page)
  await page.locator('.pw-block-insertion').tap({ position: { x: 4, y: 4 } })
  await expect(page.locator('.ce-block')).toHaveCount(2)
  await button.tap()
  await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toBeFocused()
  await page.keyboard.type('Touch paragraph')
  await expect(page.locator('.ce-paragraph')).toHaveText('Touch paragraph')
  await expect(page.locator('.tc-cell')).toHaveText('Keep cell')
})

test('native touch text selection preserves mixed paragraph and list boundaries through undo', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'BEFORE|CUT' } },
    { type: 'list', data: { style: 'unordered', items: [
      { content: 'CUT|AFTER', meta: {}, items: [] }, { content: 'OTHER', meta: {}, items: [] },
    ] } },
  ])
  await page.locator('.ce-paragraph').tap()
  // Represent the range delivered by native selection handles across editing hosts.
  await page.locator('.ce-paragraph').evaluate(field => {
    const end = document.querySelector('.cdx-list__item-content')!
    getSelection()!.setBaseAndExtent(field.firstChild!, 7, end.firstChild!, 4)
  })
  await expect.poll(() => page.evaluate(() => CSS.highlights.has('pw-text-selection'))).toBe(true)
  await page.locator('.ce-paragraph').evaluate(field => field.dispatchEvent(
    new InputEvent('beforeinput', { inputType: 'deleteContentBackward', bubbles: true, cancelable: true }),
  ))
  await expect(page.locator('.ce-paragraph')).toHaveText('BEFORE|')
  await expect(page.locator('.cdx-list__item-content')).toHaveText(['AFTER', 'OTHER'])
  await page.locator('.ce-paragraph').evaluate(field => field.dispatchEvent(
    new InputEvent('beforeinput', { inputType: 'historyUndo', bubbles: true, cancelable: true }),
  ))
  await expect(page.locator('.ce-paragraph')).toHaveText('BEFORE|CUT')
  await expect(page.locator('.cdx-list__item-content')).toHaveText(['CUT|AFTER', 'OTHER'])
})

test('touch insertion stays in the gutter without expanding text or mixing with block actions', async ({ editorPage: page }, testInfo) => {
  for (const width of [390, 320]) {
    await page.setViewportSize({ width, height: 844 })
    await render(page, [
      { type: 'paragraph', data: { text: 'First paragraph' } },
      { type: 'paragraph', data: { text: 'Second paragraph' } },
      { type: 'header', data: { text: 'Section', level: 2 } },
      { type: 'paragraph', data: { text: 'Text below the heading' } },
    ])
    await expectInsertionPreservesLayout(page)
    const paragraph = page.locator('.ce-paragraph').nth(1)
    await paragraph.tap()
    const text = (await paragraph.boundingBox())!
    const button = page.getByRole('button', { name: 'Add paragraph', exact: true }).first()
    const rule = await page.locator('.pw-block-insertion').first().evaluate(element =>
      ['::before', '::after'].map(pseudo => getComputedStyle(element, pseudo).content),
    )
    expect(rule).toEqual(['none', 'none'])
    const insertion = (await button.boundingBox())!
    const icon = (await button.locator('svg').boundingBox())!
    expect(icon.x + icon.width / 2 - insertion.x - insertion.width / 2).toBe(2)
    expect(insertion.width).toBeGreaterThanOrEqual(24)
    expect(insertion.height).toBe(44)
    expect(insertion.x).toBeGreaterThanOrEqual(text.x + text.width)
    expect(insertion.x + insertion.width).toBeLessThanOrEqual(width)
    const actions = page.locator('.ce-toolbar__actions')
    await insideViewport(actions)
    const toolbar = (await actions.boundingBox())!
    expect(toolbar.y).toBeGreaterThan(text.y + text.height)
    await page.screenshot({ path: testInfo.outputPath(`compact-insertion-${width}.png`) })
    await button.tap()
    await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toBeFocused()
    await expect(actions).toBeHidden()
    await expect(page.locator('.ce-block')).toHaveCount(5)
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width)
  }
})

test('the plus inserts text between a Mermaid diagram and an H3 on touch screens', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'codeBlock', data: { html: 'graph TD\nA --> B', language: 'mermaid' } },
    { type: 'header', data: { text: 'Keep H3', level: 3 } },
  ])
  await expect(page.locator('.pw-mermaid-preview svg')).toBeVisible()
  await page.getByRole('button', { name: 'Add paragraph', exact: true }).tap()
  await expect(page.locator('.ce-block').nth(1).locator('.ce-paragraph')).toBeFocused()
  await page.keyboard.type('Touch paragraph')
  await expect(page.locator('h3.ce-header')).toHaveText('Keep H3')
  await expect(page.locator('.ce-paragraph')).toHaveText('Touch paragraph')
  await expect(page.locator('.pw-block-insertion')).toHaveCount(2)
})

async function insideViewport(panel: Locator): Promise<void> {
  await expect
    .poll(() =>
      panel.evaluate((el) => {
        const rect = el.getBoundingClientRect()
        return (
          rect.left >= 0 &&
          rect.top >= 0 &&
          rect.right <= innerWidth &&
          rect.bottom <= innerHeight
        )
      }),
    )
    .toBe(true)
}

test('touch navigation keeps its toggle reachable and its last item scrollable', async ({
  page,
}) => {
  await page.goto('/admin/page')
  expect(
    await page.evaluate(
      () => matchMedia('(pointer: coarse)').matches && navigator.maxTouchPoints > 0,
    ),
  ).toBe(true)
  const toggle = page.getByRole('button', { name: 'Toggle navigation' })
  const sidebar = page.locator('.ea-sidebar')
  await expect(toggle).toHaveCSS('min-height', '44px')
  await toggle.tap()
  await expect(toggle).toHaveAttribute('aria-expanded', 'true')
  await expect
    .poll(() => sidebar.evaluate((el) => el.getBoundingClientRect().top))
    .toBe(48)
  const links = sidebar.locator('.ea-sidebar-item-link:visible')
  expect(
    await links.evaluateAll((elements) =>
      elements.every((el) => el.getBoundingClientRect().height >= 44),
    ),
  ).toBe(true)
  const lastLink = sidebar.getByRole('link', { name: 'Template Editor' })
  await lastLink.scrollIntoViewIfNeeded()
  await expect(lastLink).toBeInViewport()
  await toggle.tap()
  await expect(toggle).toHaveAttribute('aria-expanded', 'false')
  await toggle.tap()
  await page.locator('.ea-sidebar-backdrop.show').tap({ position: { x: 380, y: 400 } })
  await expect(toggle).toHaveAttribute('aria-expanded', 'false')
  await expect(page.locator('.ea-sidebar-backdrop')).toHaveCount(0)
})

test('list controls keep their visual size inside larger touch targets', async ({
  page,
}) => {
  await page.goto('/admin/page')
  await expect(page.locator('.action-new')).toHaveCSS('min-height', '44px')
  const checkbox = page.locator('.form-batch-checkbox').first()
  expect(
    await checkbox.evaluate((el) => {
      const rect = el.getBoundingClientRect()
      return document.elementFromPoint(rect.x - 8, rect.y + rect.height / 2) === el
    }),
  ).toBe(true)
  await checkbox.tap({ position: { x: -8, y: 7 } })
  await expect(checkbox).toBeChecked()
  const toggle = page.locator('.ea-switch-input').first()
  await expect(toggle).toHaveCSS('width', '44px')
  await expect(toggle).toHaveCSS('height', '44px')
  await expect(page.locator('td[data-column="holdPublicationAt"]').first()).toHaveCSS(
    'min-height',
    '44px',
  )
})

test('page and activity tables fit mobile landscape widths', async ({ page }) => {
  for (const width of [768, 844]) {
    await page.setViewportSize({ width, height: 390 })
    for (const path of ['/admin/page', '/admin/version-log']) {
      await page.goto(path)
      if (path.endsWith('/version-log')) {
        await page
          .locator('td[data-column="title"]')
          .first()
          .evaluate((el) => {
            el.textContent =
              'Editor submission regression checkeditor-submission-regression-check'
          })
        await page
          .locator('td[data-column="host"]')
          .first()
          .evaluate((el) => {
            el.textContent = 'admin-block-editor.test'
          })
        await page
          .locator('td[data-column="editor"]')
          .first()
          .evaluate((el) => {
            el.textContent = 'admin@example.tld'
          })
      }
      await expect
        .poll(() => page.evaluate(() => document.documentElement.scrollWidth))
        .toBeLessThanOrEqual(width)
      await expect(page.locator('.datagrid tbody tr').first()).toBeVisible()
      await expect(
        page.locator('.datagrid tbody tr').first().locator('.dropdown-toggle'),
      ).toBeInViewport()
    }
  }
})

test('long generator errors stay inside a bounded mobile alert', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 568 })
  await page.goto('/admin/page')
  await page.locator('.content-body').evaluate((el) => {
    const output = document.createElement('div')
    output.id = 'static-output-content'
    const alert = document.createElement('div')
    alert.className = 'alert alert-warning'
    alert.setAttribute('role', 'status')
    alert.textContent = Array(12)
      .fill(`Failed to open /srv/cache/Container${'x'.repeat(200)}Service.php`)
      .join('\n')
    output.append(alert)
    el.replaceChildren(output)
  })
  await expect
    .poll(() => page.evaluate(() => document.documentElement.scrollWidth))
    .toBeLessThanOrEqual(320)
  const alert = page.getByRole('status')
  await insideViewport(alert)
  expect(await alert.evaluate((el) => el.clientHeight < el.scrollHeight)).toBe(true)
  await alert.evaluate((el) => {
    el.scrollTop = el.scrollHeight
  })
  expect(await alert.evaluate((el) => el.scrollTop > 0)).toBe(true)
})

test('touch settings and insertion remain usable on small and short screens', async ({
  editorPage: page,
}) => {
  for (const [width, height] of [
    [390, 844],
    [320, 568],
    [390, 390],
  ]) {
    await page.setViewportSize({ width, height })
    await render(page, [{ type: 'paragraph', data: { text: 'Touch editing.' } }])
    await page.locator('.ce-paragraph').tap()
    await page.locator('.ce-toolbar__settings-btn').tap()
    const settings = page.locator(
      '.ce-settings .ce-popover--opened > .ce-popover__container',
    )
    await insideViewport(settings)
    await settings.getByLabel('Anchor', { exact: true }).fill('mobile-section')
    const center = settings.getByRole('button', { name: 'Align center', exact: true })
    await expect(center).toHaveCSS('min-width', '44px')
    await expect(center).toHaveCSS('min-height', '44px')
    await center.tap()
    await expect(center).toHaveAttribute('aria-pressed', 'true')
    const deletion = settings.locator('[data-item-name="delete"]')
    await deletion.scrollIntoViewIfNeeded()
    await deletion.tap()
    await expect(deletion).toHaveText('Confirm deletion')
    await expect(page.locator('.ce-block')).toHaveCount(1)
    await page.locator('.ce-popover__overlay:visible').tap({ position: { x: 5, y: 5 } })
    await expect(settings).toBeHidden()
    await page.locator('.ce-paragraph').tap()
    await page.locator('.ce-toolbar__plus').tap()
    const toolbox = page.locator(
      '.ce-toolbox .ce-popover--opened > .ce-popover__container',
    )
    await insideViewport(toolbox)
    await toolbox.getByRole('textbox', { name: 'Filter' }).fill('Table')
    await toolbox.locator('.ce-popover-item:visible').tap()
    await expect(page.locator('.tc-wrap')).toBeVisible()
    await expect
      .poll(() => page.evaluate(() => document.documentElement.scrollWidth))
      .toBeLessThanOrEqual(width)
  }
})

test('table column options respond to touch without a hover', async ({
  editorPage: page,
}) => {
  await render(page, [
    {
      type: 'table',
      data: {
        content: [
          ['Title', 'Other'],
          ['A', 'B'],
        ],
      },
    },
  ])
  await page.locator('.tc-cell').first().tap()
  await page.getByRole('button', { name: 'Column options', exact: true }).tap()
  const menu = page.locator('.tc-toolbox--column .tc-popover--opened')
  await insideViewport(menu)
  await menu.getByRole('menuitemradio', { name: 'Align center', exact: true }).tap()
  await expect(page.locator('.tc-cell').first()).toHaveCSS('text-align', 'center')
  await page.getByRole('button', { name: 'Column options', exact: true }).tap()
  await menu.getByRole('menuitem', { name: 'Delete column', exact: true }).tap()
  await expect(menu.getByRole('menuitem', { name: 'Confirm deletion' })).toBeVisible()
  await expect(page.locator('.tc-cell')).toHaveCount(4)
})

test('inline link sheets remain reachable with touch', async ({ editorPage: page }) => {
  await page.setViewportSize({ width: 320, height: 568 })
  await render(page, [{ type: 'paragraph', data: { text: 'Select this text.' } }])
  const paragraph = page.locator('.ce-paragraph')
  await paragraph.tap()
  // Represent the selection supplied by Android's native text-selection handles.
  await paragraph.evaluate((el) => {
    const range = document.createRange()
    range.selectNodeContents(el)
    const selection = window.getSelection()!
    selection.removeAllRanges()
    selection.addRange(range)
  })
  await paragraph.dispatchEvent('mouseup')
  const inline = page.locator('.ce-popover--inline.ce-popover--opened')
  await insideViewport(inline.locator(':scope > .ce-popover__container'))
  await inline.getByRole('button', { name: 'Link', exact: true }).tap()
  const links = inline.locator(
    '.ce-popover__container:has(> .ce-popover__items .link-options-wrapper)',
  )
  await insideViewport(links)
  await links.getByRole('combobox', { name: 'Page or URL' }).fill('/contact')
  await expect(links.getByRole('combobox', { name: 'Style', exact: true })).toBeVisible()
})

test('the outline and media picker open and close by touch', async ({
  editorPage: page,
}) => {
  await render(page, [{ type: 'header', data: { text: 'Mobile section', level: 2 } }])
  await page.locator('.pw-outline-opener').tap()
  await expect(page.locator('.pw-outline')).toBeVisible()
  await page.locator('.pw-outline-label').first().tap()
  await expect(page.locator('.pw-outline')).toBeHidden()
  const headerImage = page.getByRole('button', { name: 'Header image', exact: true })
  if ((await headerImage.getAttribute('aria-expanded')) !== 'true')
    await headerImage.tap()
  await page.locator('[data-pw-media-picker-action="choose"]:visible').last().tap()
  const modal = page.locator('#pw-media-picker-modal')
  await expect(modal).toBeVisible()
  await insideViewport(modal.locator('.modal-dialog'))
  const frame = page.frameLocator('#pw-media-picker-modal iframe')
  await expect(frame.getByRole('heading', { name: 'Medias', exact: true })).toBeVisible()
  await expect(frame.getByRole('searchbox')).toBeVisible()
  expect(await frame.locator('html').evaluate((el) => el.scrollWidth <= innerWidth)).toBe(
    true,
  )
  await modal.getByRole('button', { name: 'Close', exact: true }).tap()
  await expect(modal).toBeHidden()
})

test('compact link actions preserve touch selection and expose editing on a narrow screen', async ({ editorPage: page }) => {
  await page.setViewportSize({ width: 320, height: 850 })
  await render(page, [{ type: 'paragraph', data: { text: 'Avant <a href="/original">example mon texte sélectionné suite</a> après.' } }])
  const field = page.locator('.ce-paragraph')
  await field.tap()
  // Native touch selection can cancel the pointer gesture before delivering its range.
  await field.dispatchEvent('pointerdown', { pointerType: 'touch' })
  await field.dispatchEvent('pointercancel', { pointerType: 'touch' })
  await field.locator('a').evaluate(anchor => {
    getSelection()!.setBaseAndExtent(anchor.firstChild!, 8, anchor.firstChild!, 29)
  })
  const context = page.locator('.pw-link-context')
  await expect(context).toBeVisible()
  await insideViewport(context)
  await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon texte sélectionné')
  await expect(field).toBeFocused()
  await context.getByRole('button', { name: 'Edit link', exact: true }).tap()
  const url = context.getByRole('combobox', { name: 'Page or URL' })
  await expect(url).toBeFocused()
  await insideViewport(context)
  await url.fill('/nouveau')
  await url.press('Enter')
  await expect(field.locator('a')).toHaveAttribute('href', '/nouveau')
  await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon texte sélectionné')
  await expect(context).toBeHidden()
})

test('Mermaid editing keeps a compact live preview and a tappable Done button', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'codeBlock', data: { html: 'flowchart LR\nA[Before] --> B[After]', language: 'mermaid' } },
  ])
  const block = page.locator('.pw-code-block')
  await expect(block.locator('.pw-mermaid-preview svg')).toBeVisible()
  await block.getByRole('button', { name: 'Edit diagram' }).tap()
  await expect(block.getByRole('textbox', { name: 'Mermaid source', exact: true })).toBeFocused()
  const done = block.getByRole('button', { name: 'Done', exact: true })
  expect((await done.boundingBox())!.height).toBeGreaterThanOrEqual(44)
  const preview = (await block.locator('.pw-mermaid-preview').boundingBox())!
  const code = (await block.locator('.pw-code-source').boundingBox())!
  expect(preview.y + preview.height).toBeLessThanOrEqual(code.y + 1)
  expect(preview.height).toBeLessThanOrEqual(160)
  await page.keyboard.press('Control+a')
  await page.keyboard.type('flowchart LR\nA[Mobile] --> B[Preview]')
  await expect(block.locator('.pw-mermaid-preview svg')).toContainText('Mobile')
  await done.tap()
  await expect(block.locator('.pw-code-source')).toBeHidden()
})

test('Mermaid fullscreen keeps source and live preview usable on touch screens', async ({ editorPage: page }, testInfo) => {
  await page.setViewportSize({ width: 320, height: 850 })
  await render(page, [{ type: 'codeBlock', data: { html: 'flowchart LR\nA[Before] --> B[After]', language: 'mermaid' } }])
  const block = page.locator('.pw-code-block')
  await block.getByRole('button', { name: 'Edit diagram' }).tap()
  await expect(block.getByRole('textbox', { name: 'Mermaid source', exact: true })).toBeFocused()
  const overflow = await page.evaluate(() => getComputedStyle(document.documentElement).overflow)
  const expand = block.getByRole('button', { name: 'Enter fullscreen' })
  expect((await expand.boundingBox())!.width).toBeGreaterThanOrEqual(44)
  expect((await expand.boundingBox())!.height).toBeGreaterThanOrEqual(44)
  await expand.tap()
  const workspace = block.getByRole('dialog', { name: 'Mermaid editor', exact: true })
  expect(await workspace.boundingBox()).toEqual({ x: 0, y: 0, width: 320, height: 850 })
  const preview = workspace.getByRole('region', { name: 'Diagram preview', exact: true })
  const diagram = (await preview.boundingBox())!
  const code = (await workspace.locator('.pw-code-source').boundingBox())!
  expect(diagram.y + diagram.height).toBeLessThanOrEqual(code.y + 1)
  expect(diagram.height).toBeGreaterThan(200)
  expect((await workspace.locator('.monaco-editor').boundingBox())!.height).toBeGreaterThan(250)
  expect(await workspace.evaluate(node => node.scrollWidth)).toBeLessThanOrEqual(320)
  await page.keyboard.press('Control+a')
  await page.keyboard.type('flowchart LR\nA[Fullscreen] --> B[Mobile]')
  await expect(preview.locator('svg')).toContainText('Fullscreen')
  await page.screenshot({ path: testInfo.outputPath('mermaid-fullscreen-mobile.png') })
  await page.setViewportSize({ width: 844, height: 390 })
  await expect.poll(() => workspace.evaluate(node => node.clientWidth)).toBe(844)
  expect((await workspace.locator('.monaco-editor').boundingBox())!.height).toBeGreaterThan(200)
  await workspace.getByRole('button', { name: 'Exit fullscreen' }).tap()
  await expect(workspace).toBeHidden()
  await expect(block.locator('.pw-code-source')).toBeVisible()
  expect(await page.evaluate(() => getComputedStyle(document.documentElement).overflow)).toBe(overflow)
  await expect(block.locator('.pw-mermaid-preview svg')).toContainText('Fullscreen')
})
