import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { setupEditorPopovers } from './editorPopovers'

beforeAll(() => {
  document.body.innerHTML =
    '<div class="ce-popover"><button class="ce-popover-item" data-item-name="bold"></button><input class="cdx-search-field__input" placeholder="Rechercher"></div>'
  setupEditorPopovers({ Bold: 'Gras', Link: 'Lien', Filter: 'Rechercher' })
})

afterEach(() => document.body.replaceChildren())

const flushObserver = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('popover accessibility', () => {
  it('names controls that already exist when initialized', () => {
    expect(document.querySelector('button')?.getAttribute('aria-label')).toBe('Gras')
    expect(document.querySelector('input')?.getAttribute('aria-label')).toBe('Rechercher')
  })

  it('names inserted inline tools and refreshes the deletion confirmation name', async () => {
    document.body.innerHTML =
      '<div class="ce-popover"><div class="ce-popover-item"><span class="ce-popover-item__title">Delete</span></div><div class="ce-popover-item-html" data-item-name="link"><button type="button"></button></div></div>'
    await flushObserver()
    const item = document.querySelector<HTMLElement>('.ce-popover-item')!
    expect(item.title).toBe('Delete')
    expect(document.querySelector('button')?.getAttribute('aria-label')).toBe('Lien')
    item.querySelector('span')!.textContent = 'Confirm deletion'
    await flushObserver()
    expect(item.getAttribute('aria-label')).toBe('Confirm deletion')
  })

  it('lets settings controls activate and move the caret before the document flipper', () => {
    document.body.innerHTML =
      '<div class="ce-settings"><div class="ce-popover-item-html"><button type="button"></button><input><textarea></textarea></div></div>'
    const button = document.querySelector('button')!
    const field = document.querySelector('input')!
    const activate = vi.fn()
    const flipper = vi.fn()
    button.addEventListener('click', activate)
    document.addEventListener('keydown', flipper, true)
    try {
      for (const key of ['Enter', ' ']) {
        button.dispatchEvent(
          new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }),
        )
      }
      expect(activate).toHaveBeenCalledTimes(2)
      const arrow = new KeyboardEvent('keydown', {
        key: 'ArrowLeft',
        bubbles: true,
        cancelable: true,
      })
      field.dispatchEvent(arrow)
      expect(arrow.defaultPrevented).toBe(false)
      const enter = new KeyboardEvent('keydown', {
        key: 'Enter',
        bubbles: true,
        cancelable: true,
      })
      field.dispatchEvent(enter)
      expect(enter.defaultPrevented).toBe(true)
      const newline = new KeyboardEvent('keydown', {
        key: 'Enter',
        bubbles: true,
        cancelable: true,
      })
      document.querySelector('textarea')!.dispatchEvent(newline)
      expect(newline.defaultPrevented).toBe(false)
      expect(flipper).not.toHaveBeenCalled()

      // Inline link fields have their own Enter and suggestion handlers.
      document.querySelector('.ce-settings')!.className = 'ce-inline-toolbar'
      const linkEnter = vi.fn()
      field.addEventListener('keydown', linkEnter)
      field.dispatchEvent(
        new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }),
      )
      expect(linkEnter).toHaveBeenCalledOnce()
      expect(flipper).toHaveBeenCalledOnce()
    } finally {
      document.removeEventListener('keydown', flipper, true)
    }
  })

  it('adds one mobile search field, filters tools and restores them after an empty search', async () => {
    document.body.innerHTML =
      '<div class="ce-toolbox"><div class="ce-popover"><div class="ce-popover__overlay"></div><div class="ce-popover__container"><div class="ce-popover__nothing-found-message">Aucun résultat</div><div class="ce-popover__items"><div class="ce-popover-item">Tableau</div><div class="ce-popover-item">Image</div></div></div></div></div>'
    await flushObserver()
    const search = document.querySelector('input')!
    expect(search.getAttribute('aria-label')).toBe('Rechercher')
    search.value = 'table'
    search.dispatchEvent(new Event('input'))
    expect(
      document.querySelectorAll('.ce-popover-item:not(.ce-popover-item--hidden)'),
    ).toHaveLength(1)
    search.value = 'absent'
    search.dispatchEvent(new Event('input'))
    expect(
      document.querySelector('.ce-popover__nothing-found-message--displayed'),
    ).not.toBeNull()
    search.value = ''
    search.dispatchEvent(new Event('input'))
    expect(
      document.querySelectorAll('.ce-popover-item:not(.ce-popover-item--hidden)'),
    ).toHaveLength(2)
    await flushObserver()
    expect(document.querySelectorAll('input')).toHaveLength(1)
  })

  it('lets a focused URL preview open before the document flipper handles Enter', () => {
    document.body.innerHTML =
      '<div class="link-suggestions__preview"><a href="https://example.com" target="_blank">Destination</a></div>'
    const flipper = vi.fn()
    document.addEventListener('keydown', flipper, true)
    try {
      const enter = new KeyboardEvent('keydown', {
        key: 'Enter',
        bubbles: true,
        cancelable: true,
      })
      document.querySelector('a')!.dispatchEvent(enter)
      expect(enter.defaultPrevented).toBe(false)
      expect(flipper).not.toHaveBeenCalled()
    } finally {
      document.removeEventListener('keydown', flipper, true)
    }
  })
})
