import { test, expect, render, saveAndReload } from './fixtures'

const source = 'flowchart LR\nA[Brouillon] --> B[Relecture]\nB --> C[Publié]'

test('Mermaid reads as a diagram, edits live, and saves only its source', async ({ editorPage: page }, testInfo) => {
  await page.setViewportSize({ width: 1920, height: 1080 })
  await render(page, [{ type: 'codeBlock', data: { html: source, language: 'mermaid' } }])
  const block = page.locator('.pw-code-block')
  const preview = block.locator('.pw-mermaid-preview')
  await expect(preview.locator('svg')).toBeVisible()
  await expect(block.locator('.pw-code-source')).toBeHidden()
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
