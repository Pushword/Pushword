import { test, expect, render, saveAndReload } from './fixtures'

const source = 'flowchart LR\nA[Brouillon] --> B[Relecture]\nB --> C[Publié]'

test('Mermaid reads as a diagram, edits live, and saves only its source', async ({ editorPage: page }, testInfo) => {
  await page.setViewportSize({ width: 1920, height: 1080 })
  await render(page, [{ type: 'codeBlock', data: { html: source, language: 'mermaid' } }])
  const block = page.locator('.pw-code-block')
  const preview = block.locator('.pw-mermaid-preview')
  await expect(preview.locator('svg')).toBeVisible()
  await expect(block.locator('.pw-code-source')).toBeHidden()
  await expect(block.getByRole('status')).toHaveCount(0)
  const readingHeight = (await block.boundingBox())!.height
  await block.getByRole('button', { name: 'Edit diagram' }).click()
  const input = block.getByRole('textbox', { name: 'Mermaid source', exact: true })
  await expect(input).toBeFocused()
  await expect(block.locator('.monaco-editor')).toBeVisible()
  const code = (await block.locator('.pw-code-source').boundingBox())!
  const diagram = (await preview.boundingBox())!
  expect(code.x + code.width).toBeLessThanOrEqual(diagram.x + 1)
  expect(Math.abs(code.y - diagram.y)).toBeLessThan(1)
  await page.keyboard.press('Control+a')
  await page.keyboard.type(source.replace('Relecture', 'Validation'))
  await expect(preview.locator('svg')).toContainText('Validation')
  await page.screenshot({ path: testInfo.outputPath('mermaid-editing.png') })
  await page.keyboard.press('Escape')
  await expect(block.getByRole('button', { name: 'Edit diagram' })).toBeFocused()
  await expect(block.locator('.pw-code-source')).toBeHidden()
  expect((await block.boundingBox())!.height).toBeLessThan(code.height)
  expect(readingHeight).toBeLessThan(code.height)
  await saveAndReload(page)
  await expect(preview.locator('svg')).toContainText('Validation')
  await expect(block.locator('.pw-code-source')).toBeHidden()
  const saved = await page.evaluate(async () => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
    return (await window.editors[holder.id]!.save()).blocks[0]!.data
  })
  expect(saved).toEqual({ html: source.replace('Relecture', 'Validation'), language: 'mermaid' })
})

test('Mermaid keeps the last valid preview while the source has a syntax error', async ({ editorPage: page }) => {
  await render(page, [{ type: 'codeBlock', data: { html: source, language: 'mermaid' } }])
  const block = page.locator('.pw-code-block')
  const preview = block.locator('.pw-mermaid-preview')
  await expect(preview.locator('svg')).toBeVisible()
  await block.getByRole('button', { name: 'Edit diagram' }).click()
  await expect(block.getByRole('textbox', { name: 'Mermaid source', exact: true })).toBeFocused()
  await page.keyboard.press('Control+a')
  await page.keyboard.type('flowchart LR\n  A[')
  await expect(block.getByRole('alert')).toContainText('Unable to render the Mermaid diagram.')
  await expect(block.getByRole('status')).toHaveText('Last valid preview')
  await expect(preview.locator('svg')).toContainText('Relecture')
  await expect(block.getByRole('textbox', { name: 'Mermaid source', exact: true })).toHaveAttribute('aria-invalid', 'true')
  await page.keyboard.press('Control+a')
  await page.keyboard.type(source)
  await expect(block.getByRole('alert')).toBeHidden()
  await expect(block.getByRole('status')).toHaveText('Live preview')
})

for (const width of [1280, 390, 320]) {
  test(`Mermaid keeps a compact live preview above the source at ${width}px`, async ({ editorPage: page }, testInfo) => {
    await page.setViewportSize({ width, height: 844 })
    await render(page, [{ type: 'codeBlock', data: { html: source, language: 'mermaid' } }])
    const block = page.locator('.pw-code-block')
    await expect(block.locator('.pw-mermaid-preview svg')).toBeVisible()
    await block.getByRole('button', { name: 'Edit diagram' }).click()
    await expect(block.getByRole('textbox', { name: 'Mermaid source', exact: true })).toBeFocused()
    const preview = (await block.locator('.pw-mermaid-preview').boundingBox())!
    const code = (await block.locator('.pw-code-source').boundingBox())!
    expect(preview.y + preview.height).toBeLessThanOrEqual(code.y + 1)
    expect(preview.height).toBeLessThanOrEqual(160)
    expect(await block.evaluate(element => element.scrollWidth)).toBeLessThanOrEqual(width)
    await page.keyboard.press('Control+a')
    await page.keyboard.type(source.replace('Relecture', 'Mobile'))
    await expect(block.locator('.pw-mermaid-preview svg')).toContainText('Mobile')
    await page.screenshot({ path: testInfo.outputPath(`mermaid-${width}.png`) })
    await block.getByRole('button', { name: 'Done', exact: true }).click()
    await expect(block.locator('.pw-code-source')).toBeHidden()
  })
}

test('Mermaid keeps tall and wide diagrams readable and reachable by keyboard', async ({ editorPage: page }, testInfo) => {
  for (const width of [1920, 320]) {
    await page.setViewportSize({ width, height: 1080 })
    for (const direction of ['TD', 'LR']) {
      await test.step(`${direction} at ${width}px`, async () => {
        const source = `flowchart ${direction}\n` + Array.from({ length: 12 }, (_, index) =>
          `S${index}[Step ${index + 1}] --> S${index + 1}[Step ${index + 2}]`,
        ).join('\n')
        await render(page, [{ type: 'codeBlock', data: { html: source, language: 'mermaid' } }])
        const block = page.locator('.pw-code-block')
        const preview = block.getByRole('region', { name: 'Diagram preview', exact: true })
        const svg = preview.locator('svg')
        await expect(svg).toBeVisible()
        const scale = () => svg.evaluate(node => (node as SVGSVGElement).getScreenCTM()!.a)
        expect(await scale(), 'Reading must preserve legible diagram detail').toBeGreaterThanOrEqual(0.84)
        await block.getByRole('button', { name: 'Edit diagram' }).click()
        await expect(block.getByRole('textbox', { name: 'Mermaid source', exact: true })).toBeFocused()
        expect(await scale(), 'Editing must preserve legible diagram detail').toBeGreaterThanOrEqual(0.84)
        expect((await preview.boundingBox())!.height).toBeLessThanOrEqual(width === 320 ? 160 : 384)
        expect(await block.evaluate(node => node.scrollWidth)).toBeLessThanOrEqual(width)
        const first = (await preview.locator('.node').first().boundingBox())!
        const frame = (await preview.boundingBox())!
        expect(first.x).toBeGreaterThanOrEqual(frame.x)
        expect(first.y).toBeGreaterThanOrEqual(frame.y)
        await preview.focus()
        await expect(preview).toBeFocused()
        await preview.press(direction === 'TD' ? 'ArrowDown' : 'ArrowRight')
        await expect(page.locator('.ce-block')).toHaveCount(1)
        await expect.poll(() => preview.evaluate((node, vertical) =>
          vertical ? node.scrollTop : node.scrollLeft, direction === 'TD',
        )).toBeGreaterThan(0)
        await preview.evaluate((node, vertical) => {
          if (vertical) node.scrollTop = node.scrollHeight
          else node.scrollLeft = node.scrollWidth
        }, direction === 'TD')
        const last = (await preview.locator('.node').last().boundingBox())!
        const end = (await preview.boundingBox())!
        expect(last.x + last.width).toBeLessThanOrEqual(end.x + end.width)
        expect(last.y + last.height).toBeLessThanOrEqual(end.y + end.height)
        await page.screenshot({ path: testInfo.outputPath(`mermaid-${direction}-${width}.png`) })
      })
    }
  }
})

test('Mermaid keeps an initial error concise above its editable source on mobile', async ({ editorPage: page }) => {
  await page.setViewportSize({ width: 320, height: 850 })
  await render(page, [{ type: 'codeBlock', data: { html: 'flowchart LR\nA[', language: 'mermaid' } }])
  const block = page.locator('.pw-code-block')
  await expect(block.getByRole('status')).toHaveText('Preview unavailable')
  await expect(block.getByRole('alert')).toContainText('Unable to render the Mermaid diagram.')
  const done = block.getByRole('button', { name: 'Done', exact: true })
  const button = (await done.boundingBox())!
  const status = (await block.getByRole('status').boundingBox())!
  expect(status.y).toBeGreaterThanOrEqual(button.y + button.height)
  expect(await block.evaluate(node => node.scrollWidth)).toBe(await block.evaluate(node => node.clientWidth))
  await done.click()
  await expect(block.getByRole('status')).toBeVisible()
  await expect(block.locator('.pw-code-source')).toBeHidden()
})

test('Mermaid fullscreen fills the viewport, preserves its cursor and edits live', async ({ editorPage: page }, testInfo) => {
  await page.setViewportSize({ width: 1280, height: 900 })
  await render(page, [{ type: 'codeBlock', data: { html: source, language: 'mermaid' } }])
  const block = page.locator('.pw-code-block')
  await expect(block.getByRole('button', { name: 'Enter fullscreen' })).toHaveCount(0)
  await block.getByRole('button', { name: 'Edit diagram' }).click()
  const input = block.getByRole('textbox', { name: 'Mermaid source', exact: true })
  await expect(input).toBeFocused()
  await page.keyboard.press('Control+End')
  await page.keyboard.press('ArrowLeft')
  const before = await page.evaluate(() => {
    const editor = window.monaco!.editor.getEditors().find(editor => editor.getModel()?.getLanguageId() === 'mermaid')!
    return { id: editor.getModel()!.id, selection: editor.getSelection() }
  })
  await block.getByRole('button', { name: 'Enter fullscreen' }).click()
  const workspace = block.getByRole('dialog', { name: 'Mermaid editor', exact: true })
  await expect(workspace).toBeVisible()
  await expect(input).toBeFocused()
  const bounds = (await workspace.boundingBox())!
  expect(bounds).toEqual({ x: 0, y: 0, width: 1280, height: 900 })
  const code = (await workspace.locator('.pw-code-source').boundingBox())!
  const preview = workspace.getByRole('region', { name: 'Diagram preview', exact: true })
  const diagram = (await preview.boundingBox())!
  expect(code.x + code.width).toBeLessThanOrEqual(diagram.x + 1)
  expect(Math.abs(code.y - diagram.y)).toBeLessThan(1)
  expect((await workspace.locator('.monaco-editor').boundingBox())!.height).toBeGreaterThan(600)
  expect(await page.evaluate(() => {
    const editor = window.monaco!.editor.getEditors().find(editor => editor.getModel()?.getLanguageId() === 'mermaid')!
    return { id: editor.getModel()!.id, selection: editor.getSelection() }
  })).toEqual(before)
  await page.keyboard.type(' live')
  await expect(preview.locator('svg')).toContainText('Publié live')
  await page.keyboard.press('Control+a')
  await page.keyboard.type('flowchart LR\nA[')
  await expect(workspace.getByRole('alert')).toBeVisible()
  await expect(preview.locator('svg')).toContainText('Publié live')
  const error = (await workspace.getByRole('alert').boundingBox())!
  expect(error.y + error.height).toBeLessThanOrEqual(bounds.height)
  await page.keyboard.press('Control+a')
  await page.keyboard.type(source.replace('Publié', 'Publié live'))
  await expect(workspace.getByRole('alert')).toBeHidden()
  await workspace.getByRole('button', { name: 'Done', exact: true }).focus()
  await page.keyboard.press('Shift+Tab')
  await expect(preview).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(workspace.getByRole('button', { name: 'Done', exact: true })).toBeFocused()
  await page.screenshot({ path: testInfo.outputPath('mermaid-fullscreen.png') })
  await workspace.getByRole('button', { name: 'Exit fullscreen' }).click()
  await expect(workspace).toBeHidden()
  await expect(block.getByRole('button', { name: 'Enter fullscreen' })).toBeFocused()
  await expect(block.locator('.pw-code-source')).toBeVisible()
  await block.getByRole('button', { name: 'Enter fullscreen' }).click()
  await expect(input).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(workspace).toBeHidden()
  await expect(block.locator('.pw-code-source')).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(block.locator('.pw-code-source')).toBeHidden()
  await saveAndReload(page)
  await expect(block.locator('.pw-mermaid-preview svg')).toContainText('Publié live')
})

test('Done closes Mermaid fullscreen and returns directly to reading', async ({ editorPage: page }) => {
  await render(page, [{ type: 'codeBlock', data: { html: source, language: 'mermaid' } }])
  const block = page.locator('.pw-code-block')
  await block.getByRole('button', { name: 'Edit diagram' }).click()
  await expect(block.getByRole('textbox', { name: 'Mermaid source', exact: true })).toBeFocused()
  await block.getByRole('button', { name: 'Enter fullscreen' }).click()
  await block.getByRole('dialog').getByRole('button', { name: 'Done', exact: true }).click()
  await expect(block.getByRole('dialog')).toBeHidden()
  await expect(block.getByRole('button', { name: 'Edit diagram' })).toBeFocused()
  await expect(block.locator('.pw-code-source')).toBeHidden()
  await expect(block.locator('.pw-mermaid-preview svg')).toBeVisible()
})

test('Mermaid fullscreen preserves Ctrl+Enter paragraph insertion', async ({ editorPage: page }) => {
  await render(page, [{ type: 'codeBlock', data: { html: source, language: 'mermaid' } }])
  const block = page.locator('.pw-code-block')
  await block.getByRole('button', { name: 'Edit diagram' }).click()
  await expect(block.getByRole('textbox', { name: 'Mermaid source', exact: true })).toBeFocused()
  await block.getByRole('button', { name: 'Enter fullscreen' }).click()
  await expect(block.getByRole('textbox', { name: 'Mermaid source', exact: true })).toBeFocused()
  await page.keyboard.press('Control+Enter')
  await expect(block.getByRole('dialog')).toBeHidden()
  await expect(page.locator('.ce-paragraph')).toBeFocused()
  await expect(page.locator('.ce-block')).toHaveCount(2)
  await expect(block.locator('.pw-mermaid-preview svg')).toBeVisible()
})
