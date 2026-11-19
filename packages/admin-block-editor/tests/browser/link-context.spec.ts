import type { Page } from '@playwright/test'
import { test, expect, render, saveAndReload } from './fixtures'

const text = "example mon texte sélectionné suite de l'ancre"

async function selectLink(page: Page, start = 8, end = 29, backward = false): Promise<void> {
  const field = page.locator('.ce-paragraph').first()
  await field.focus()
  await field.locator('a').first().evaluate((anchor, { start, end, backward }) => {
    const node = anchor.firstChild!
    getSelection()!.setBaseAndExtent(node, backward ? end : start, node, backward ? start : end)
  }, { start, end, backward })
}

async function copy(page: Page, text: string): Promise<void> {
  await page.evaluate(text => {
    const source = document.createElement('textarea')
    source.id = 'link-clipboard-source'
    source.value = text
    document.body.append(source)
  }, text)
  await page.locator('#link-clipboard-source').focus()
  await page.keyboard.press('Control+a')
  await page.keyboard.press('Control+c')
  await page.locator('#link-clipboard-source').evaluate(element => element.remove())
}

for (const backward of [false, true]) {
  for (const replacement of ['le texte collé', 'https://example.com/autre']) {
    test(`a ${backward ? 'backward' : 'forward'} selection inside a link accepts ${replacement.startsWith('https') ? 'a URL' : 'text'} without changing its address`, async ({ editorPage: page }) => {
      await render(page, [{ type: 'paragraph', data: { text: `Avant <a href="#lien" target="_blank" rel="nofollow" class="link-btn">${text}</a> après.` } }])
      await copy(page, replacement)
      await selectLink(page, 8, 29, backward)
      await expect(page.locator('.pw-link-context')).toBeVisible()
      await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon texte sélectionné')
      await expect(page.locator('.ce-paragraph')).toBeFocused()
      await page.keyboard.press('Control+v')
      const field = page.locator('.ce-paragraph')
      await expect(field).toHaveText(`Avant example ${replacement} suite de l'ancre après.`)
      await expect(field.locator('a')).toHaveCount(1)
      await expect(field.locator('a')).toHaveAttribute('href', '#lien')
      await expect(field.locator('a')).toHaveAttribute('rel', 'nofollow')
      await expect(field.locator('a')).toHaveAttribute('target', '_blank')
      await expect(field.locator('a')).toHaveAttribute('class', 'link-btn')
      await page.keyboard.press('Control+z')
      await expect(field).toHaveText(`Avant ${text} après.`)
      await page.keyboard.press('Control+y')
      await expect(field).toHaveText(`Avant example ${replacement} suite de l'ancre après.`)
      await saveAndReload(page)
      await expect(field.locator('a')).toHaveAttribute('href', '#lien')
      await expect(field).toHaveText(`Avant example ${replacement} suite de l'ancre après.`)
    })
  }
}

for (const key of ['Backspace', 'Delete']) {
  test(`${key} removes only the selected part of a link`, async ({ editorPage: page }) => {
    await render(page, [{ type: 'paragraph', data: { text: `Avant <a href="#lien">${text}</a> après.` } }])
    await selectLink(page)
    await expect(page.locator('.pw-link-context')).toBeVisible()
    await page.keyboard.press(key)
    await expect(page.locator('.ce-paragraph a')).toHaveText("example  suite de l'ancre")
    await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '#lien')
    await page.keyboard.press('Control+z')
    await expect(page.locator('.ce-paragraph a')).toHaveText(text)
  })
}

test('a caret shows compact link actions, with explicit editing and Escape dismissal', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: `Avant <a href="#lien">${text}</a> après.` } }])
  await selectLink(page, 15, 15)
  const context = page.locator('.pw-link-context')
  await expect(context).toBeVisible()
  await expect(context.getByRole('link')).toHaveAttribute('href', '#lien')
  await expect(context.getByRole('combobox', { name: 'Page or URL' })).toBeHidden()
  await expect(page.locator('.ce-paragraph')).toBeFocused()
  await page.keyboard.press('ArrowRight')
  await expect.poll(() => page.evaluate(() => getSelection()?.anchorOffset)).toBe(16)
  await context.getByRole('button', { name: 'Edit link', exact: true }).click()
  const url = context.getByRole('combobox', { name: 'Page or URL' })
  await expect(url).toBeFocused()
  await url.fill('/nouvelle-adresse')
  await url.press('Enter')
  await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '/nouvelle-adresse')
  await expect(page.locator('.ce-paragraph')).toBeFocused()
  await expect.poll(() => page.evaluate(() => getSelection()?.anchorOffset)).toBe(16)
  await expect(context).toBeHidden()
  await page.keyboard.press('ArrowRight')
  await expect(context).toBeHidden()
  await page.keyboard.press('Control+k')
  await expect(url).toBeFocused()
  await url.press('Escape')
  await expect(context).toBeHidden()
  await expect(page.locator('.ce-paragraph')).toBeFocused()
  await page.keyboard.press('ArrowRight')
  await expect(context).toBeHidden()
  await page.keyboard.press('End')
  await selectLink(page, 10, 10)
  await expect(context).toBeVisible()
})

test('editing or removing the link preserves a backward text selection', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: `Avant <a href="#lien">${text}</a> après.` } }])
  await selectLink(page, 8, 29, true)
  const context = page.locator('.pw-link-context')
  await expect(context).toBeVisible()
  await page.keyboard.press('Control+k')
  const url = context.getByRole('combobox', { name: 'Page or URL' })
  await expect(url).toBeFocused()
  await url.fill('/autre')
  await url.press('Enter')
  await expect.poll(() => page.evaluate(() => ({ text: getSelection()?.toString(), backward: getSelection()!.anchorOffset > getSelection()!.focusOffset }))).toEqual({ text: 'mon texte sélectionné', backward: true })
  await page.keyboard.press('End')
  await selectLink(page, 8, 29, true)
  await context.getByRole('button', { name: 'Remove link', exact: true }).click()
  await expect(page.locator('.ce-paragraph a')).toHaveCount(0)
  await expect(page.locator('.ce-paragraph')).toHaveText(`Avant ${text} après.`)
  await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon texte sélectionné')
  await page.keyboard.press('Control+z')
  await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '/autre')
})

test('a selection crossing a link boundary keeps ordinary formatting tools', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: 'Avant <a href="/one">texte</a> et <a href="/two">texte</a> après.' } }])
  const field = page.locator('.ce-paragraph')
  await field.focus()
  await field.evaluate(el => {
    const links = el.querySelectorAll('a')
    getSelection()!.setBaseAndExtent(links[0]!.firstChild!, 0, links[1]!.firstChild!, 5)
  })
  await expect(page.locator('.ce-inline-toolbar [data-item-name="bold"]')).toBeVisible()
  await expect(page.locator('.pw-link-context')).toBeHidden()
  await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('texte et texte')
  await page.locator('.ce-inline-toolbar [data-item-name="bold"]').click()
  await expect(field).toHaveText('Avant texte et texte après.')
  await expect(field.locator('a')).toHaveCount(2)
})

for (const backward of [false, true]) {
  test(`a paused ${backward ? 'backward' : 'forward'} mouse drag keeps an exact selection within a link`, async ({ editorPage: page }) => {
    await render(page, [{ type: 'paragraph', data: { text: `Avant <a href="#lien">${text}</a> après.` } }])
    await page.locator('.ce-paragraph').scrollIntoViewIfNeeded()
    const points = await page.locator('.ce-paragraph a').evaluate(anchor => [8, 29].map(offset => {
      const range = document.createRange()
      range.setStart(anchor.firstChild!, offset)
      range.collapse(true)
      const box = range.getBoundingClientRect()
      return { x: box.x + 0.1, y: box.y + box.height / 2 }
    }))
    if (backward) points.reverse()
    await page.mouse.move(points[0]!.x, points[0]!.y)
    await page.mouse.down()
    await page.mouse.move(points[1]!.x, points[1]!.y, { steps: 20 })
    await page.waitForTimeout(350)
    await expect(page.locator('.pw-link-context')).toBeHidden()
    await page.mouse.up()
    await expect(page.locator('.pw-link-context')).toBeVisible()
    await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon texte sélectionné')
    await expect(page.locator('.ce-paragraph')).toBeFocused()
    await page.locator('.ce-inline-toolbar [data-item-name="italic"]').click()
    await expect(page.locator('.ce-paragraph a i, .ce-paragraph a em')).toHaveText('mon texte sélectionné')
    await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '#lien')
  })
}

test('pasting a formatted selection from another link keeps the destination address', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: `Avant <a href="#lien">${text}</a> après.` } },
    { type: 'paragraph', data: { text: '<a href="/source"><b>le texte collé</b></a>' } },
  ])
  const source = page.locator('.ce-paragraph').nth(1)
  await source.focus()
  await source.locator('b').evaluate(el => {
    getSelection()!.setBaseAndExtent(el.firstChild!, 0, el.firstChild!, el.textContent!.length)
  })
  await page.keyboard.press('Control+c')
  await selectLink(page)
  await expect(page.locator('.pw-link-context')).toBeVisible()
  await page.keyboard.press('Control+v')
  const destination = page.locator('.ce-paragraph').first()
  await expect(destination).toHaveText("Avant example le texte collé suite de l'ancre après.")
  await expect(destination.locator('a')).toHaveCount(1)
  await expect(destination.locator('a')).toHaveAttribute('href', '#lien')
  await expect(destination.locator('b, strong')).toHaveText('le texte collé')
})

test('link address drafts use native undo without undoing the paragraph', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: `Avant <a href="#lien">${text}</a> après.` } }])
  await selectLink(page)
  await page.keyboard.press('Control+k')
  const url = page.locator('.pw-link-context input.link-options__url')
  await expect(url).toBeFocused()
  await url.press('Control+a')
  await page.keyboard.insertText('/draft-address')
  await page.keyboard.press('Control+z')
  await expect(url).toHaveValue('#lien')
  await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '#lien')
  await page.keyboard.press('Control+y')
  await expect(url).toHaveValue('/draft-address')
  await url.press('Escape')
  await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '#lien')
  await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon texte sélectionné')
})

test('deleting all text in a link removes the link and closes its context', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: `Avant <a href="#lien">${text}</a> après.` } }])
  await selectLink(page, 0, text.length)
  await expect(page.locator('.pw-link-context')).toBeVisible()
  await page.keyboard.press('Delete')
  await expect(page.locator('.ce-paragraph')).toHaveText('Avant après.')
  await expect(page.locator('.ce-paragraph a')).toHaveCount(0)
  await expect(page.locator('.pw-link-context')).toBeHidden()
})

test('destroying an editor removes its link context and pending refresh', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: `<a href="#lien">${text}</a>` } }])
  await selectLink(page, 10, 10)
  await expect(page.locator('.pw-link-context')).toBeVisible()
  await page.evaluate(() => {
    const holder = document.querySelector<HTMLElement>('.editorjs-holder')!
    window.editors[holder.id]!.destroy()
  })
  await expect(page.locator('.pw-link-context')).toHaveCount(0)
  await page.waitForTimeout(300)
  await expect(page.locator('.pw-link-context')).toHaveCount(0)
})

test('a selection spanning links in separate blocks does not choose one link for editing', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: 'Avant <a href="/one">premier lien</a>' } },
    { type: 'paragraph', data: { text: '<a href="/two">second lien</a> après.' } },
  ])
  const points = await page.locator('.ce-paragraph a').evaluateAll(links => links.map((link, index) => {
    const range = document.createRange()
    range.setStart(link.firstChild!, index === 0 ? 3 : 6)
    range.collapse(true)
    const box = range.getBoundingClientRect()
    return { x: box.x + 0.1, y: box.y + box.height / 2 }
  }))
  await page.mouse.move(points[0]!.x, points[0]!.y)
  await page.mouse.down()
  await page.mouse.move(points[1]!.x, points[1]!.y, { steps: 20 })
  await page.mouse.up()
  await page.waitForTimeout(350)
  await expect(page.locator('.pw-link-context')).toBeHidden()
  await expect(page.locator('.ce-paragraph a').first()).toHaveAttribute('href', '/one')
  await expect(page.locator('.ce-paragraph a').last()).toHaveAttribute('href', '/two')
})

test('the inline Link command edits an existing link without removing it', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: `Avant <a href="#lien">${text}</a> après.` } }])
  await selectLink(page)
  await page.locator('.ce-inline-toolbar [data-item-name="link"]').click()
  const url = page.locator('.ce-inline-toolbar .link-options__url')
  await expect(url).toBeFocused()
  await expect(page.locator('.pw-link-context')).toBeHidden()
  await url.fill('/inline-edited')
  await url.press('Enter')
  await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '/inline-edited')
  await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon texte sélectionné')
})

test('link property changes undo from their controls without retaining a stale form', async ({ editorPage: page }) => {
  await render(page, [{ type: 'paragraph', data: { text: `Avant <a href="#lien">${text}</a> après.` } }])
  await selectLink(page)
  await page.keyboard.press('Control+k')
  const context = page.locator('.pw-link-context')
  const style = context.getByRole('combobox', { name: 'Style', exact: true })
  await style.selectOption('link-btn')
  await expect(page.locator('.ce-paragraph a')).toHaveAttribute('class', 'link-btn')
  await style.press('Control+z')
  await expect(page.locator('.ce-paragraph a')).not.toHaveAttribute('class')
  await expect(context.locator('.link-options-wrapper')).toHaveCount(0)
  await page.keyboard.press('Control+y')
  await expect(page.locator('.ce-paragraph a')).toHaveAttribute('class', 'link-btn')
})

test('leaving a changed link address commits it without moving the new caret', async ({ editorPage: page }) => {
  await render(page, [
    { type: 'paragraph', data: { text: `<a href="#lien">${text}</a>` } },
    { type: 'paragraph', data: { text: 'Continue here' } },
  ])
  await selectLink(page)
  await page.keyboard.press('Control+k')
  await page.locator('.pw-link-context input.link-options__url').fill('/committed')
  const next = page.locator('.ce-paragraph').last()
  await next.click()
  await expect(next).toBeFocused()
  await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '/committed')
  await expect(page.locator('.pw-link-context')).toBeHidden()
})

test('a narrow link panel keeps its full address row and right edge clickable beside the scrollbar', async ({ editorPage: page }) => {
  await page.setViewportSize({ width: 320, height: 850 })
  const href = 'https://example.org/voyages/une-randonnee-en-montagne?depart=2026-10-01&participants=12'
  await render(page, [{ type: 'paragraph', data: { text: `<a href="${href}">${text}</a>` } }])
  await selectLink(page)
  const context = page.locator('.pw-link-context')
  await expect(context).toBeVisible()
  expect((await context.locator('.pw-link-context__address').boundingBox())!.width).toBeGreaterThan(240)
  const edgeIsClickable = () => context.evaluate(panel => {
    const bounds = panel.getBoundingClientRect()
    return panel.contains(document.elementFromPoint(bounds.right - 4, bounds.top + 30))
  })
  await expect.poll(edgeIsClickable).toBe(true)
  await context.getByRole('button', { name: 'Edit link', exact: true }).click()
  await expect.poll(edgeIsClickable).toBe(true)
  const url = context.getByRole('combobox', { name: 'Page or URL' })
  await expect(url).toBeFocused()
  await expect(context.getByRole('status')).toBeHidden()
  await expect(context.getByRole('link')).toHaveAttribute('href', href)
  await url.fill('/changed')
  await context.getByRole('button', { name: 'Done', exact: true }).click()
  await expect(context).toBeHidden()
  await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '/changed')
  await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon texte sélectionné')
})

for (const href of ['http://%', 'javascript:alert(1)']) {
  test(`invalid address ${href} stays visible as an error and keeps the original link`, async ({ editorPage: page }) => {
    await render(page, [{ type: 'paragraph', data: { text: `<a href="#lien">${text}</a>` } }])
    await selectLink(page)
    await page.keyboard.press('Control+k')
    const context = page.locator('.pw-link-context')
    const url = context.getByRole('combobox', { name: 'Page or URL' })
    await url.fill(href)
    await url.press('Enter')
    await expect(context.getByRole('alert')).toHaveText('Enter a valid URL.')
    await expect(url).toHaveAttribute('aria-invalid', 'true')
    await expect(url).toBeFocused()
    await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '#lien')
    await context.getByRole('button', { name: 'Done', exact: true }).click()
    await expect(context).toBeVisible()
    await expect(url).toBeFocused()
    await url.fill('/corrected')
    await expect(url).not.toHaveAttribute('aria-invalid')
    await context.getByRole('button', { name: 'Done', exact: true }).click()
    await expect(context).toBeHidden()
    await expect(page.locator('.ce-paragraph a')).toHaveAttribute('href', '/corrected')
    await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon texte sélectionné')
  })
}

for (const activation of ['click', 'Enter', 'Space']) {
  test(`Done activated by ${activation} creates a new link and restores its text range`, async ({ editorPage: page }) => {
    await render(page, [{ type: 'paragraph', data: { text: 'mon texte' } }])
    const field = page.locator('.ce-paragraph')
    await field.focus()
    await field.evaluate(el => getSelection()!.setBaseAndExtent(el.firstChild!, 0, el.firstChild!, 9))
    const toolbar = page.locator('.ce-inline-toolbar')
    await toolbar.locator('[data-item-name="link"]').click()
    const url = toolbar.getByRole('combobox', { name: 'Page or URL' })
    const done = toolbar.getByRole('button', { name: 'Done', exact: true })
    await url.fill('http://%')
    await done.click()
    await expect(toolbar.getByRole('alert')).toBeVisible()
    await expect(field.locator('a')).toHaveCount(0)
    await url.fill('/destination')
    if (activation === 'click') await done.click()
    else await done.press(activation)
    await expect(field.locator('a')).toHaveAttribute('href', '/destination')
    await expect(field.locator('a')).toHaveText('mon texte')
    await expect.poll(() => page.evaluate(() => getSelection()?.toString())).toBe('mon texte')
  })
}
