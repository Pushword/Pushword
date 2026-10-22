import { test, expect, render } from './fixtures'
import type { Locator } from '@playwright/test'

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
  await page.locator('.modal-backdrop').tap({ position: { x: 380, y: 400 } })
  await expect(toggle).toHaveAttribute('aria-expanded', 'false')
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
