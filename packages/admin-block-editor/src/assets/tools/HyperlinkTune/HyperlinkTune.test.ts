import { expect, it, vi } from 'vitest'
import HyperlinkTune from './HyperlinkTune'

it('keeps a named image link field and switch help when editing a saved URL', () => {
  const dispatchChange = vi.fn()
  const tool = new HyperlinkTune({
    api: { i18n: { t: (text: string) => `t(${text})` }, saver: { save: vi.fn() } } as any,
    block: { dispatchChange } as any,
    data: { url: '/original', hideForBot: true },
    readOnly: false,
  })
  const panel = tool.render()
  const field = panel.querySelector<HTMLElement>('[role="textbox"]')!
  expect(field).not.toBeNull()
  expect(
    panel
      .querySelector(`[id="${field.getAttribute('aria-labelledby')}"]`)
      ?.textContent?.trim(),
  ).toBe('t(Link)')
  expect(field.textContent).toBe('/original')
  const switchInput = panel.querySelector<HTMLInputElement>('input[role="switch"]')!
  expect(switchInput.checked).toBe(true)
  expect(
    panel.querySelector(`[id="${switchInput.getAttribute('aria-describedby')}"]`)
      ?.textContent,
  ).toBe('t(Mask the link in the page source.)')
  field.textContent = '/new'
  field.dispatchEvent(new Event('input'))
  expect(tool.save()).toEqual({ url: '/new', hideForBot: true, targetBlank: false })
  expect(dispatchChange).toHaveBeenCalledOnce()
})
