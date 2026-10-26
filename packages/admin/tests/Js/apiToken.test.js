// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { initApiTokenFields } from '../../src/Resources/assets/admin.apiToken.js'

function render(token = '') {
  document.body.innerHTML = `<form><div data-api-token-widget
    data-regenerate-confirm="Replace token?" data-revoke-confirm="Revoke token?" data-copied-label="Copied">
    <input readonly value="${token}">
    <button type="button" data-token-action="generate">Generate</button>
    <button type="button" data-token-action="copy"><span>Copy</span></button>
    <button type="button" data-token-action="regenerate">Regenerate</button>
    <button type="button" data-token-action="revoke">Revoke</button>
  </div></form>`
  initApiTokenFields()
  return document.querySelector('input')
}

const action = (name) => document.querySelector(`[data-token-action="${name}"]`)
const visibleActions = () =>
  [...document.querySelectorAll('button')]
    .filter((button) => !button.hidden)
    .map((button) => button.dataset.tokenAction)

describe('API token actions', () => {
  beforeEach(() => {
    vi.stubGlobal(
      'confirm',
      vi.fn(() => true),
    )
    vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockResolvedValue() } })
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('offers generation only for an empty token', () => {
    render()
    expect(visibleActions()).toEqual(['generate'])
  })

  it('never offers generation over an existing token', () => {
    render('existing-token')
    expect(visibleActions()).toEqual(['copy', 'regenerate', 'revoke'])
  })

  it('generates a 64-character token, notifies the form and moves focus to copy', () => {
    const input = render()
    const form = input.form
    const onInput = vi.fn()
    const onChange = vi.fn()
    form.addEventListener('input', onInput)
    form.addEventListener('change', onChange)
    action('generate').click()

    expect(input.value).toMatch(/^[a-f0-9]{64}$/)
    expect(onInput).toHaveBeenCalledOnce()
    expect(onChange).toHaveBeenCalledOnce()
    expect(visibleActions()).toEqual(['copy', 'regenerate', 'revoke'])
    expect(document.activeElement).toBe(action('copy'))
  })

  it.each(['regenerate', 'revoke'])(
    'preserves the token when %s is cancelled',
    (name) => {
      const input = render('existing-token')
      confirm.mockReturnValue(false)
      action(name).click()

      expect(confirm).toHaveBeenCalledWith(
        name === 'regenerate' ? 'Replace token?' : 'Revoke token?',
      )
      expect(input.value).toBe('existing-token')
      expect(visibleActions()).toEqual(['copy', 'regenerate', 'revoke'])
    },
  )

  it('replaces the token after confirmation', () => {
    const input = render('existing-token')
    action('regenerate').click()
    expect(confirm).toHaveBeenCalledWith('Replace token?')
    expect(input.value).toMatch(/^[a-f0-9]{64}$/)
    expect(visibleActions()).toEqual(['copy', 'regenerate', 'revoke'])
  })

  it('clears the token after confirmation and restores generation focus', () => {
    const input = render('existing-token')
    action('revoke').click()
    expect(confirm).toHaveBeenCalledWith('Revoke token?')
    expect(input.value).toBe('')
    expect(visibleActions()).toEqual(['generate'])
    expect(document.activeElement).toBe(action('generate'))
  })

  it('copies the current value and restores its label even after repeated clicks', async () => {
    vi.useFakeTimers()
    const input = render('existing-token')
    action('copy').click()
    await Promise.resolve()
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(input.value)
    expect(action('copy').textContent).toBe('Copied')
    action('copy').click()
    await Promise.resolve()
    vi.runAllTimers()
    expect(action('copy').textContent).toBe('Copy')
  })
})
