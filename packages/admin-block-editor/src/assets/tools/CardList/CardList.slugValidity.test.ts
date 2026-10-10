// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { API } from '@editorjs/editorjs'
import CardList from './CardList'

afterEach(() => {
  vi.useRealTimers()
  document.body.innerHTML = ''
  window.pagesUriList = []
})

function renderCard(page: string): { tool: CardList; input: HTMLInputElement; error: HTMLElement } {
  window.pagesUriList = ['/known']
  const api = {
    i18n: { t: (text: string) => text },
    styles: { block: 'cdx-block' },
  } as unknown as API
  const tool = new CardList({ data: { items: [{ page }] }, api, readOnly: false })
  const root = tool.render()
  document.body.appendChild(root)
  const input = root.querySelector<HTMLInputElement>('.cardlist-slug-input')!
  const error = root.querySelector<HTMLElement>('.cardlist-slug-error')!
  return { tool, input, error }
}

describe('CardList slug feedback', () => {
  it('clears the accessible error as soon as the user starts correcting a slug', () => {
    const { tool, input, error } = renderCard('missing')
    expect(tool.validate()).toBe(false)
    expect(error.hidden).toBe(false)

    input.value = 'known'
    input.dispatchEvent(new Event('input'))

    expect(input.hasAttribute('aria-invalid')).toBe(false)
    expect(input.classList.contains('cardlist-slug-invalid')).toBe(false)
    expect(error.hidden).toBe(true)
    expect(tool.validate()).toBe(true)
  })

  it('shows an unknown slug after blur and accepts an empty replacement', () => {
    vi.useFakeTimers()
    const { input, error } = renderCard('missing')
    input.dispatchEvent(new Event('blur'))
    expect(error.hidden).toBe(true)

    vi.advanceTimersByTime(500)
    expect(input.getAttribute('aria-invalid')).toBe('true')
    expect(error.hidden).toBe(false)

    input.value = ''
    input.dispatchEvent(new Event('input'))
    input.dispatchEvent(new Event('blur'))
    vi.advanceTimersByTime(500)
    expect(input.hasAttribute('aria-invalid')).toBe(false)
    expect(error.hidden).toBe(true)
  })
})
