import { test, expect, render } from './fixtures'
import type { Locator, Page } from '@playwright/test'

async function openSettings(page: Page, block = 0): Promise<Locator> {
  const target = page.locator('.ce-block').nth(block)
  await target.scrollIntoViewIfNeeded()
  await target.hover()
  await target.locator('[contenteditable]').first().focus()
  await page.locator('.ce-toolbar__settings-btn').click()
  const panel = page.locator('.ce-settings .ce-popover--opened > .ce-popover__container')
  await expect(panel).toBeVisible()
  return panel
}

async function insideViewport(panel: Locator): Promise<void> {
  await expect
    .poll(() =>
      panel.evaluate((el) => {
        const r = el.getBoundingClientRect()
        return (
          r.left >= 0 && r.top >= 0 && r.right <= innerWidth && r.bottom <= innerHeight
        )
      }),
    )
    .toBe(true)
}

async function selectText(page: Page): Promise<void> {
  const paragraph = page.locator('.ce-paragraph').first()
  await paragraph.scrollIntoViewIfNeeded()
  await paragraph.focus()
  await paragraph.evaluate((el) => {
    const range = document.createRange()
    range.selectNodeContents(el)
    const selection = window.getSelection()!
    selection.removeAllRanges()
    selection.addRange(range)
  })
  await paragraph.dispatchEvent('mouseup')
  await expect(
    page.locator('.ce-popover--inline.ce-popover--opened > .ce-popover__container'),
  ).toBeVisible()
}

test('the URL preview opens a new tab without changing the edited link', async ({
  editorPage: page,
}) => {
  const url = 'https://example.com/plans?products=choice%2Ctravel&sort=popularity'
  await page.context().route(url, route => route.fulfill({ body: 'Destination preview' }))
  await render(page, [
    { type: 'paragraph', data: { text: `<a href="${url}">Get a quote</a>` } },
  ])
  const paragraph = page.locator('.ce-paragraph').first()
  await paragraph.focus()
  await paragraph.locator('a').evaluate(el => {
    const range = document.createRange()
    range.selectNodeContents(el)
    const selection = window.getSelection()!
    selection.removeAllRanges()
    selection.addRange(range)
  })
  await paragraph.dispatchEvent('mouseup')
  const options = page.locator('.link-options-wrapper')
  await expect(options).toBeVisible()
  await options.locator('select').first().focus()
  const preview = options.locator('.link-suggestions__preview a')
  await expect(preview).toHaveText(url)
  await expect(preview).toHaveAttribute('href', url)
  await expect(preview).toHaveAttribute('target', '_blank')
  const editorUrl = page.url()

  for (const activate of [() => preview.click(), () => preview.press('Enter')]) {
    const popupPromise = page.waitForEvent('popup')
    await activate()
    const popup = await popupPromise
    await expect(popup).toHaveURL(url)
    expect(await popup.evaluate(() => window.opener)).toBeNull()
    await popup.close()
    await expect(page).toHaveURL(editorUrl)
    await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', url)
    await expect(page.locator('.ce-paragraph a')).not.toHaveAttribute('target')
  }
})

for (const form of ['page', 'snippet']) {
  test(`${form} link options escape the fieldset and remain clickable over settings`, async ({
    editorPage: page,
  }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    if (form === 'snippet') {
      await page.goto('/admin/snippet/new')
      await page.waitForFunction(() => {
        const holder = document.querySelector<HTMLElement>('.editorjs-holder')
        return holder && window.editors?.[holder.id]?.blocks
      })
    }
    await render(page, [
      {
        type: 'paragraph',
        data: { text: 'Link' },
        tunes: { textAlign: 'right' },
      },
    ])
    await selectText(page)
    await page
      .locator('.ce-popover--inline.ce-popover--opened')
      .getByRole('button', { name: 'Link', exact: true })
      .click()
    const options = page.locator('.link-options-wrapper')
    await expect(options).toBeVisible()
    await expect
      .poll(() =>
        options.evaluate((el) => {
          const rect = el.getBoundingClientRect()
          const fieldset = el.closest('.form-fieldset-body')!.getBoundingClientRect()
          const x = fieldset.right + 8
          return (
            x < rect.right &&
            el.contains(document.elementFromPoint(x, rect.top + rect.height / 2))
          )
        }),
      )
      .toBe(true)
  })
}

test('open link and table menus stay within the viewport after resizing', async ({
  editorPage: page,
}) => {
  for (const tool of ['link', 'table']) {
    await page.setViewportSize({ width: 1440, height: 900 })
    await render(page, [
      {
        type: 'paragraph',
        data: { text: Array(20).fill('Content before the menu.').join('<br>') },
      },
      { type: 'paragraph', data: { text: 'Resize while editing this link.' } },
      { type: 'table', data: { content: [['First column', 'Last column']] } },
    ])
    let menu: Locator
    if (tool === 'link') {
      const paragraph = page.locator('.ce-paragraph').last()
      await paragraph.scrollIntoViewIfNeeded()
      await paragraph.focus()
      await paragraph.evaluate((el) => {
        const range = document.createRange()
        range.selectNodeContents(el)
        const selection = window.getSelection()!
        selection.removeAllRanges()
        selection.addRange(range)
      })
      await paragraph.dispatchEvent('mouseup')
      await page.getByRole('button', { name: 'Link', exact: true }).click()
      menu = page.locator(
        '.ce-popover__container:has(> .ce-popover__items .link-options-wrapper)',
      )
    } else {
      const cell = page.locator('.tc-cell').first()
      await cell.scrollIntoViewIfNeeded()
      await cell.click()
      await cell.hover()
      await page.getByRole('button', { name: 'Column options', exact: true }).click()
      menu = page.locator('.tc-toolbox--column .tc-popover--opened')
    }
    await expect(menu).toBeVisible()
    for (const size of [
      { width: 800, height: 600 },
      { width: 1024, height: 300 },
      { width: 375, height: 568 },
    ]) {
      await page.setViewportSize(size)
      await insideViewport(menu)
      await expect
        .poll(() =>
          menu.evaluate((el) => {
            const rect = el.getBoundingClientRect()
            return el.contains(document.elementFromPoint(rect.right - 8, rect.bottom - 8))
          }),
        )
        .toBe(true)
    }
    await page.keyboard.press('Escape')
  }
})

test('block settings keep labels, alignment state and a translated deletion confirmation', async ({
  editorPage: page,
}) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'First paragraph.' } },
    { type: 'paragraph', data: { text: 'Second paragraph.' } },
  ])
  const panel = await openSettings(page)
  await insideViewport(panel)
  await expect(panel.getByLabel('Anchor', { exact: true })).toBeVisible()
  await panel.getByLabel('Anchor', { exact: true }).fill('section')
  await expect(panel.locator('.cdx-anchor-tune-label').first()).toHaveText('Anchor')
  await expect(panel.getByLabel('Class', { exact: true })).toBeVisible()
  await panel.getByLabel('Class', { exact: true }).pressSequentially('wide layout')
  await expect(panel.getByLabel('Class', { exact: true })).toHaveValue('wide layout')
  const center = panel.getByRole('button', { name: 'Align center', exact: true })
  await expect(
    panel.getByRole('button', { name: 'Align left', exact: true }),
  ).toHaveAttribute('aria-pressed', 'true')
  await center.focus()
  await page.keyboard.press('Space')
  await expect(center).toHaveAttribute('aria-pressed', 'true')
  await expect(page.locator('.editorjs-holder .ce-block__content').first()).toHaveClass(
    /text-center/,
  )
  const right = panel.getByRole('button', { name: 'Align right', exact: true })
  await right.focus()
  await page.keyboard.press('Enter')
  await expect(right).toHaveAttribute('aria-pressed', 'true')
  await expect(page.locator('.ce-block')).toHaveCount(2)
  await expect(
    panel.locator('[data-item-name="move-down"] .ce-popover-item__title'),
  ).toBeVisible()
  await panel.locator('[data-item-name="delete"]').click()
  await expect(panel.locator('[data-item-name="delete"]')).toHaveText('Confirm deletion')
  await expect(panel.locator('[data-item-name="delete"]')).toHaveAttribute(
    'aria-label',
    'Confirm deletion',
  )
  await expect(page.locator('.ce-block')).toHaveCount(2)
  await page.keyboard.press('Escape')
  await expect(panel).toBeHidden()
})

test('insertion search filters tools and reports an empty search', async ({
  editorPage: page,
}) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Find a block.' } }])
  await page.locator('.ce-paragraph').click()
  await page.locator('.ce-toolbar__plus').click()
  const panel = page.locator('.ce-toolbox .ce-popover--opened > .ce-popover__container')
  const search = panel.getByRole('textbox', { name: 'Filter' })
  await expect(search).toBeVisible()
  await search.fill('Table')
  await expect(panel.locator('.ce-popover-item:visible')).toHaveCount(1)
  await expect(panel.locator('.ce-popover-item:visible')).toHaveText('Table')
  await search.fill('no-such-block')
  await expect(panel.getByText('Nothing found', { exact: true })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(panel).toBeHidden()
})

test('settings and insertion sheets fit narrow and short viewports without covering their own actions', async ({
  editorPage: page,
}) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Small-screen editing.' } }])
  for (const [width, height] of [
    [375, 812],
    [320, 568],
  ]) {
    await page.setViewportSize({ width, height })
    const panel = await openSettings(page)
    await insideViewport(panel)
    const action = panel.locator('[data-item-name="delete"]')
    await action.scrollIntoViewIfNeeded()
    await expect(action).toBeInViewport()
    const hit = await action.evaluate((el) => {
      const r = el.getBoundingClientRect()
      return el.contains(document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2))
    })
    expect(hit).toBe(true)
    await expect(panel.getByLabel('Anchor', { exact: true })).toHaveCSS(
      'min-height',
      '44px',
    )
    await page.keyboard.press('Escape')
    await page.locator('.ce-paragraph').click()
    await page.locator('.ce-toolbar__plus').click()
    const toolbox = page.locator(
      '.ce-toolbox .ce-popover--opened > .ce-popover__container',
    )
    await insideViewport(toolbox)
    await toolbox.getByRole('textbox', { name: 'Filter' }).fill('Quiz')
    await expect(toolbox.locator('.ce-popover-item:visible')).toHaveText('Quiz')
    await page.keyboard.press('Escape')
    expect(
      await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(width)
  }
})

test('inline tools have names and link options stay within the viewport', async ({
  editorPage: page,
}) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Select this text.' } }])
  for (const width of [1440, 768, 375, 320]) {
    await page.setViewportSize({ width, height: 812 })
    await selectText(page)
    const inline = page.locator('.ce-popover--inline.ce-popover--opened')
    await insideViewport(inline.locator(':scope > .ce-popover__container'))
    await expect(inline.getByRole('button', { name: 'Bold', exact: true })).toBeVisible()
    await inline.getByRole('button', { name: 'Convert to', exact: true }).click()
    const conversion = inline.locator('.ce-popover--nested > .ce-popover__container')
    await insideViewport(conversion)
    await expect(conversion.getByText('Heading', { exact: true })).toBeVisible()
    await page.keyboard.press('Escape')
    await selectText(page)
    await inline.getByRole('button', { name: 'Link', exact: true }).click()
    const links = inline.locator(
      '.ce-popover__container:has(> .ce-popover__items .link-options-wrapper)',
    )
    await expect(links.getByRole('combobox', { name: 'Page or URL' })).toBeVisible()
    await insideViewport(links)
    await expect(
      links.getByRole('combobox', { name: 'Style', exact: true }),
    ).toBeVisible()
    await page.keyboard.press('Escape')
    await page.locator('[id$="_h1"]').click()
  }
})

test('table menus support keyboard navigation, show alignment and cancel deletion on Escape', async ({
  editorPage: page,
}) => {
  for (const width of [1440, 768, 320]) {
    await page.setViewportSize({ width, height: 812 })
    await render(page, [
      {
        type: 'paragraph',
        data: { text: Array(24).fill('A line before the table.').join('<br>') },
      },
      {
        type: 'table',
        data: {
          withHeadings: true,
          content: [
            ['Title', 'Other'],
            ['A', 'B'],
          ],
          columnAlignments: ['center', ''],
        },
      },
    ])
    await page.locator('.tc-cell').first().scrollIntoViewIfNeeded()
    await page.locator('.tc-cell').first().click()
    await page.locator('.tc-cell').first().hover()
    const toggle = page.getByRole('button', { name: 'Column options', exact: true })
    await toggle.click()
    const menu = page.locator('.tc-toolbox--column .tc-popover--opened')
    await insideViewport(menu)
    await expect(
      menu.getByRole('menuitemradio', { name: 'Align center', exact: true }),
    ).toHaveAttribute('aria-checked', 'true')
    await expect(menu.getByRole('menuitem', { name: 'Add column to left' })).toBeFocused()
    await page.keyboard.press('End')
    await page.keyboard.press('Enter')
    await expect(menu.getByRole('menuitem', { name: 'Confirm deletion' })).toBeFocused()
    const contrast = await menu
      .getByRole('menuitem', { name: 'Confirm deletion' })
      .evaluate((el) => {
        const luminance = (color: string) => {
          const rgb = color
            .match(/[\d.]+/g)!
            .slice(0, 3)
            .map((value) => {
              const component = Number(value) / 255
              return component <= 0.04045
                ? component / 12.92
                : ((component + 0.055) / 1.055) ** 2.4
            })
          return rgb[0]! * 0.2126 + rgb[1]! * 0.7152 + rgb[2]! * 0.0722
        }
        const style = getComputedStyle(el)
        const light = luminance(style.color)
        const dark = luminance(style.backgroundColor)
        return (Math.max(light, dark) + 0.05) / (Math.min(light, dark) + 0.05)
      })
    expect(
      contrast,
      'Focused confirmation text must remain readable',
    ).toBeGreaterThanOrEqual(4.5)
    await expect(page.locator('.tc-cell')).toHaveCount(4)
    await page.keyboard.press('Escape')
    await expect(menu).toBeHidden()
    await expect(toggle).toBeFocused()
    await expect(toggle).toHaveAttribute('aria-expanded', 'false')
  }
})

test('desktop settings stay inside the viewport when opened beside a low block', async ({
  editorPage: page,
}) => {
  await render(page, [
    {
      type: 'paragraph',
      data: { text: Array(24).fill('A line before the settings.').join('<br>') },
    },
    { type: 'paragraph', data: { text: 'Open settings here.' } },
  ])
  for (const width of [1440, 768]) {
    await page.setViewportSize({ width, height: 700 })
    const panel = await openSettings(page, 1)
    await insideViewport(panel)
    await panel.locator('[data-item-name="delete"]').click()
    await insideViewport(panel)
    await page.keyboard.press('Escape')
  }
})
