import type { Locator, Page } from '@playwright/test'
import { test, expect, render } from './fixtures'

async function expectHandleBeside(page: Page, block: Locator): Promise<void> {
  await block.evaluate(block => block.scrollIntoView({ block: 'center' }))
  await page.mouse.move(0, 0)
  await block.locator('.ce-block__content').hover()
  const handle = page.locator('.ce-toolbar__settings-btn')
  await expect(handle).toBeVisible()
  await expect(handle).toBeInViewport()
  await expect.poll(async () => {
    const content = await block.locator('.ce-block__content').boundingBox()
    const button = await handle.boundingBox()
    if (!content || !button) return false
    const center = button.y + button.height / 2
    // Editor.js puts controls on the right when the editor is narrow.
    const gap = Math.max(content.x - button.x - button.width, button.x - content.x - content.width)
    return center >= content.y - 4 && center <= content.y + content.height + 4 &&
      gap >= 0 && gap <= 60
  }, { message: 'The handle must stay beside the hovered block' }).toBe(true)
}

async function savedBlocks(page: Page) {
  return page.evaluate(async () => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
    const editor = window.editors[holder.id]
    const blocks = []
    // Block.save also includes empty images, which the document saver filters out.
    for (let index = 0; index < editor.blocks.getBlocksCount(); index++) {
      const block = editor.blocks.getBlockByIndex(index)!
      blocks.push({ id: block.id, type: block.name, data: (await block.save())!.data })
    }
    return blocks
  })
}

async function moveTargetUp(page: Page, block: Locator): Promise<void> {
  const before = await savedBlocks(page)
  await expectHandleBeside(page, block)
  await page.locator('.ce-toolbar__settings-btn').click()
  await page.locator('.ce-popover-item[data-item-name="move-up"]').click()
  await expect.poll(() => savedBlocks(page)).toEqual([before[1], before[0], before[2]])
  await expect(page.locator('.editorjs-holder .ce-block').first()).toHaveAttribute('data-id', before[1]!.id)
}

for (const { name, type, data, field, value, edited } of [
  {
    name: 'PagesList',
    type: 'pages_list',
    data: { kw: 'children', display: 'card', order: 'weight ↓, publishedAt ↓', max: '3', maxPages: '2' },
    field: '.cdx-input-labeled-pageslist-kw',
    value: 'children',
    edited: 'sisters',
  },
  {
    name: 'Snippet text fields',
    type: 'snippet',
    data: { name: 'cta', params: { title: 'Keep this title', description: 'Keep this text' } },
    field: '.cdx-snippet__field input[type="text"]',
    value: 'Keep this title',
    edited: 'Edited title',
  },
  {
    name: 'Snippet JSON field',
    type: 'snippet',
    data: { name: 'custom', params: { text: 'Keep this JSON' } },
    field: '.cdx-snippet__field textarea',
    value: '{\n  "text": "Keep this JSON"\n}',
    edited: '{"text":"Edited JSON"}',
  },
]) {
  test(`${name}: preview handle, Move up and repeated editing retain the block content`, async ({ editorPage: page }) => {
    await render(page, [
      { type: 'paragraph', data: { text: 'Before the target' } },
      { id: 'handle-target', type, data },
      { type: 'paragraph', data: { text: 'After the target' } },
    ])
    const block = page.locator('.ce-block[data-id="handle-target"]')
    await expect(block.locator('.preview-wrapper')).toBeVisible()
    await moveTargetUp(page, block)

    for (const [expected, replacement] of [[value, edited], [edited, value]]) {
      await block.locator('.preview-wrapper').click()
      const input = block.locator(field).first()
      if (type === 'pages_list') await expect(input).toHaveText(expected!)
      else await expect(input).toHaveValue(expected!)
      await input.fill(replacement!)
      await block.locator('.toggle-label').click()
      await expect(block.locator('.preview-wrapper')).toBeVisible()
      await expectHandleBeside(page, block)
    }
    const saved = (await savedBlocks(page))[0]!
    expect(saved.data).toEqual(data)
  })
}

test('the preview toggle stays clickable when the narrow toolbar moves over it', async ({ editorPage: page }) => {
  await render(page, [
    { id: 'handle-target', type: 'pages_list', data: { kw: 'children' } },
  ])
  const block = page.locator('.ce-block[data-id="handle-target"]')
  // While its 0.3 s entrance fade runs, a block confines the toggle's z-index;
  // a person only reaches the toggle after it.
  await block.evaluate(block => Promise.all(block.getAnimations().map(animation => animation.finished)))
  await expect(page.locator('.codex-editor')).toHaveClass(/codex-editor--narrow/)
  await block.locator('.preview-wrapper').click()
  const toggle = block.locator('.toggle-label')
  await toggle.hover()
  await page.mouse.down()
  // Reproduce the toolbar moving between pointer down and pointer up.
  await block.evaluate(block => {
    const toolbar = block.closest('.codex-editor')!.querySelector<HTMLElement>('.ce-toolbar')!
    const actions = toolbar.querySelector<HTMLElement>('.ce-toolbar__actions')!
    toolbar.classList.add('ce-toolbar--opened')
    actions.classList.add('ce-toolbar__actions--opened')
    const target = block.querySelector('.toggle-label')!.getBoundingClientRect()
    const controls = actions.getBoundingClientRect()
    toolbar.style.top = `${toolbar.offsetTop + target.y + target.height / 2 - controls.y - controls.height / 2}px`
  })
  await page.mouse.up()
  await expect(block.locator('.preview-wrapper')).toBeVisible()
})

for (const { type, data, field } of [
  { type: 'pages_list', data: { kw: 'children' }, field: '.cdx-input-labeled-pageslist-kw' },
  {
    type: 'embed',
    data: { serviceUrl: 'https://www.youtube.com/watch?v=handle', alternativeText: 'Video', media: 'handle-test.svg' },
    field: '.cdx-input-labeled-embed-service-url',
  },
]) {
  test(`${type}: while a field is edited, the narrow handle and the preview toggle stay apart`, async ({ editorPage: page }) => {
    await page.route('**/media/**/handle-test.svg', route => route.fulfill({
      contentType: 'image/svg+xml',
      body: '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180"/>',
    }))
    await render(page, [{ id: 'handle-target', type, data }])
    await expect(page.locator('.codex-editor')).toHaveClass(/codex-editor--narrow/)
    const block = page.locator('.ce-block[data-id="handle-target"]')
    await block.locator('.preview-wrapper').click()
    // Editing lines the handle up with the first field, beside the toggle.
    await block.locator(field).click()
    await expect.poll(() => block.evaluate(block => {
      const toggle = block.querySelector('.toggle-label')!.getBoundingClientRect()
      return ['.ce-toolbar__plus', '.ce-toolbar__settings-btn'].map(selector => {
        const control = block.closest('.codex-editor')!.querySelector(selector)!.getBoundingClientRect()
        return Math.max(0, Math.min(toggle.right, control.right) - Math.max(toggle.left, control.left)) *
          Math.max(0, Math.min(toggle.bottom, control.bottom) - Math.max(toggle.top, control.top))
      })
    }), { message: 'Area of the + and the handle under the toggle' }).toEqual([0, 0])
    await page.locator('.ce-toolbar__settings-btn').click()
    await expect(page.locator('.ce-popover-item[data-item-name="move-up"]')).toBeVisible()
    await page.keyboard.press('Escape')
    await block.locator('.toggle-label').click()
    await expect(block.locator('.preview-wrapper')).toBeVisible()
  })
}

test('empty image: handle and Move up work before and after filling and removing the media', async ({ editorPage: page }) => {
  await page.route('**/media/md/handle-test.svg', route => route.fulfill({
    contentType: 'image/svg+xml',
    body: '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="120"><rect width="300" height="120" fill="navy"/></svg>',
  }))
  await render(page, [
    { type: 'paragraph', data: { text: 'Before the image' } },
    { id: 'handle-target', type: 'image', data: { media: '', caption: 'Initial caption' } },
    { type: 'paragraph', data: { text: 'After the image' } },
  ])
  const block = page.locator('.ce-block[data-id="handle-target"]')
  await expect(block.locator('.image-tool--empty')).toBeVisible()
  await moveTargetUp(page, block)

  for (const caption of ['Initial caption', 'Edited caption']) {
    await page.evaluate(() => {
      const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
      const block = window.editors[holder.id].blocks.getById('handle-target')!
      block.call('onFileLoading')
      block.call('onUpload', { success: true, file: { media: 'handle-test.svg' } })
    })
    await expect(block.locator('.image-tool--filled')).toBeVisible()
    await expect(block.locator('.image-tool__caption')).toHaveText(caption)
    await expectHandleBeside(page, block)
    // Enter the caption as a user would, closing the move menu and its block selection.
    await block.locator('.image-tool__caption').click()
    await block.locator('.image-tool__caption').fill('Edited caption')
    expect((await savedBlocks(page))[0]!.data).toEqual({ media: 'handle-test.svg', caption: 'Edited caption' })
    await block.locator('.media-tool__delete').click()
    await expect(block.locator('.image-tool--empty')).toBeVisible()
    await expect(block.locator('.cdx-button').first()).toBeVisible()
    await expectHandleBeside(page, block)
  }
})
