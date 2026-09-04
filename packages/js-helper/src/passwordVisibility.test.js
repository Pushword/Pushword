import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('password visibility', () => {
  beforeEach(() => {
    vi.resetModules()
    document.body.innerHTML = `
      <input id="secret" type="password">
      <button type="button" aria-controls="secret" aria-pressed="false" data-password-toggle>
        <span data-password-hidden-icon></span>
        <span data-password-visible-icon hidden></span>
      </button>
    `
  })

  it('toggles the input and exposes its state to assistive technology', async () => {
    const { initPasswordVisibility } = await import('./passwordVisibility.js')
    initPasswordVisibility()

    const input = document.querySelector('#secret')
    const button = document.querySelector('button')
    button.click()

    expect(input.type).toBe('text')
    expect(button.getAttribute('aria-pressed')).toBe('true')
    expect(button.querySelector('[data-password-hidden-icon]').hidden).toBe(true)
    expect(button.querySelector('[data-password-visible-icon]').hidden).toBe(false)

    button.click()
    expect(input.type).toBe('password')
    expect(button.getAttribute('aria-pressed')).toBe('false')
  })
})
