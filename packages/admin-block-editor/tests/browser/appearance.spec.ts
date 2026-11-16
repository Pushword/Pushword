import { test, expect, render } from './fixtures'

test('block text keeps readable contrast in light and dark admin themes', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'header', data: { level: 2, text: 'Section' } },
    { type: 'paragraph', data: { text: '<a href="https://example.org" rel="obfuscate">Obfuscated link</a>' } },
    { type: 'notice', data: { level: 'sponsored', title: 'Notice title', text: 'Notice <code class="inline-code">code</code>' } },
    { type: 'image', data: { media: '1.jpg', caption: 'Image caption' } },
    { type: 'groupStart', data: { anchor: 'section', class: 'grid', collapsible: false, legacy: false } },
    { type: 'paragraph', data: { text: 'Grouped content' } },
    { type: 'groupEnd', data: {} },
    { type: 'groupStart', data: { anchor: '', class: '', collapsible: true, legacy: false } },
    { type: 'paragraph', data: { text: 'Collapsible content' } },
    { type: 'groupEnd', data: {} },
    { type: 'card_list', data: { items: [{ title: 'Card title', description: 'Card description' }] } },
  ])
  for (const theme of ['light', 'dark']) {
    await page.evaluate(theme => {
      document.body.classList.toggle('ea-light-scheme', theme === 'light')
      document.body.classList.toggle('ea-dark-scheme', theme === 'dark')
    }, theme)
    for (const focused of [false, true]) {
      if (focused) await page.locator('.image-tool__caption').focus()
      else await page.locator('.ce-header').focus()
      await expect.poll(() => page.evaluate(() => {
        const canvas = document.createElement('canvas')
        canvas.width = canvas.height = 1
        const context = canvas.getContext('2d')!
        const luminance = () => {
          const rgb = [...context.getImageData(0, 0, 1, 1).data].slice(0, 3).map(value => {
            const component = value / 255
            return component <= 0.04045 ? component / 12.92 : ((component + 0.055) / 1.055) ** 2.4
          })
          return rgb[0]! * 0.2126 + rgb[1]! * 0.7152 + rgb[2]! * 0.0722
        }
        return [
          '.ce-header-level-label', '.ce-paragraph a[rel="obfuscate"]', '.cdx-notice__title', '.cdx-notice__body',
          '.cdx-notice .inline-code', '.image-tool__caption', '.pw-group-label',
          '.pw-group-input', '.cardlist-title', '.cardlist-description',
          '.pw-group-start[data-pw-group-kind="showMore"] .pw-group-label',
        ].map(selector => {
          const element = document.querySelector(selector)!
          const ancestors = []
          for (let parent: Element | null = element; parent; parent = parent.parentElement) ancestors.unshift(parent)
          context.fillStyle = '#fff'
          context.fillRect(0, 0, 1, 1)
          for (const parent of ancestors) {
            context.fillStyle = getComputedStyle(parent).backgroundColor
            context.fillRect(0, 0, 1, 1)
          }
          const background = luminance()
          context.fillStyle = getComputedStyle(element).color
          context.fillRect(0, 0, 1, 1)
          const foreground = luminance()
          return { selector, contrast: (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05) }
        }).filter(result => result.contrast < 4.5)
      }), { message: `Readable block text in the ${theme} theme, caption focused: ${focused}` }).toEqual([])
    }
  }
})

test('card, group and notice fields fit their blocks on narrow screens', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'notice', data: { level: 'important', title: 'A title worth reading', text: 'Notice body' } },
    { type: 'groupStart', data: { anchor: 'long-anchor', class: 'grid grid-cols-2 gap-8', collapsible: false, legacy: false } },
    { type: 'paragraph', data: { text: 'Grouped content' } },
    { type: 'groupEnd', data: {} },
    { type: 'card_list', data: { items: [{ title: 'Card title', link: 'https://example.org', description: 'Description', buttonLinkLabel: 'Read more' }] } },
  ])
  for (const width of [320, 375, 768]) {
    await page.setViewportSize({ width, height: 900 })
    const fields = await page.locator('.cardlist-wrapper input, .cardlist-wrapper [contenteditable="true"], .pw-group-input, .cdx-notice__input').evaluateAll(elements =>
      elements.filter(element => element.getBoundingClientRect().width > 0).map(element => {
        const field = element.getBoundingClientRect()
        const block = element.closest('.ce-block')!.getBoundingClientRect()
        return { left: field.left - block.left, right: block.right - field.right }
      }),
    )
    expect(fields).not.toHaveLength(0)
    for (const field of fields) {
      expect(field.left).toBeGreaterThanOrEqual(-1)
      expect(field.right).toBeGreaterThanOrEqual(-1)
    }
    if (width < 576) {
      for (const [field, row] of [
        ['.cardlist-slug-input', '.cardlist-item-header'],
        ['.cdx-notice__title', '.cdx-notice__header'],
      ]) {
        await expect.poll(() => page.evaluate(([field, row]) =>
          document.querySelector(field!)!.getBoundingClientRect().width -
          document.querySelector(row!)!.getBoundingClientRect().width,
        [field, row]), { message: `${field} fills its row at ${width}px` }).toBeGreaterThanOrEqual(-1)
      }
    }
  }
})

test.describe('gallery touch layout', () => {
  test.use({ hasTouch: true })

  test('gallery pictures leave room for separate touch controls', async ({ editorPage: page }) => {
    await render(page, [{ type: 'gallery', data: { items: [
      { media: '1.jpg', caption: 'First picture' },
      { media: '2.jpg', caption: 'Second picture' },
    ] } }])
    for (const width of [320, 375]) {
      await page.setViewportSize({ width, height: 900 })
      const item = page.locator('.cdxcarousel-item').first()
      const picture = (await item.boundingBox())!
      await expect(item.getByRole('button')).toHaveCount(3)
      const buttons = await item.locator('button').evaluateAll(elements => elements.map(element => element.getBoundingClientRect().toJSON()))
      for (const button of buttons) {
        expect(button.width).toBeGreaterThanOrEqual(44)
        expect(button.height).toBeGreaterThanOrEqual(44)
        expect(button.x).toBeGreaterThanOrEqual(picture.x)
        expect(button.right).toBeLessThanOrEqual(picture.x + picture.width)
      }
      for (let index = 0; index < buttons.length; index++)
        for (const other of buttons.slice(index + 1))
          expect(Math.max(buttons[index]!.x, other.x)).toBeGreaterThanOrEqual(Math.min(buttons[index]!.right, other.right))
    }
  })
})

test('gallery actions work from the keyboard without submitting the page', async ({ editorPage: page }) => {
  await render(page, [{ type: 'gallery', data: { items: [
    { media: '1.jpg', caption: 'First picture' },
    { media: '2.jpg', caption: 'Second picture' },
  ] } }])
  const formUrl = page.url()
  const move = page.getByRole('button', { name: 'Move image right', exact: true }).first()
  await move.focus()
  await expect(move).toBeFocused()
  await expect(move).toHaveCSS('outline-style', 'solid')
  await move.press('Enter')
  const captions = page.locator('.cdxcarousel-item .image-tool__caption')
  await expect(captions).toHaveText(['Second picture', 'First picture'])
  await page.getByRole('button', { name: 'Remove the media', exact: true }).first().press('Space')
  await expect(captions).toHaveText(['First picture'])
  await expect(page).toHaveURL(formUrl)
})
