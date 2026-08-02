// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { initCtrlSAutoSave } from '../../src/Resources/assets/admin.ctrlSAutoSave.js'

/**
 * The module rides htmx 4 events (colon names, status under detail.ctx) — this
 * is the wiring that silently broke on the v2 → v4 migration (detail.xhr is
 * gone under fetch).
 */
describe('initCtrlSAutoSave on htmx 4 events', () => {
  let form, indicator

  const fire = (name, status) =>
    form.dispatchEvent(
      new CustomEvent(name, {
        detail: status === undefined ? {} : { ctx: { response: { status } } },
      }),
    )

  beforeEach(() => {
    vi.useFakeTimers()
    vi.stubGlobal('htmx', {})
    document.body.innerHTML =
      '<span id="ind">Save</span>' +
      '<form data-pw-ctrl-s-form="1" data-pw-ctrl-s-indicator="#ind"></form>'
    form = document.querySelector('form')
    indicator = document.getElementById('ind')
    initCtrlSAutoSave()
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('Ctrl+S dispatches the trigger event on the form', () => {
    const triggered = vi.fn()
    form.addEventListener('pw-ctrl-s-event', triggered)
    document.dispatchEvent(
      new KeyboardEvent('keydown', { key: 's', ctrlKey: true, cancelable: true }),
    )
    expect(triggered).toHaveBeenCalledOnce()
  })

  it('shows saving on htmx:before:request', () => {
    fire('htmx:before:request')
    expect(indicator.dataset.state).toBe('saving')
    expect(indicator.textContent).toBe('Saving...')
  })

  it('reads a success status from detail.ctx.response and returns to idle', () => {
    fire('htmx:after:request', 200)
    expect(indicator.dataset.state).toBe('success')
    expect(indicator.textContent).toBe('Saved')
    vi.advanceTimersByTime(200)
    expect(indicator.dataset.state).toBe('idle')
    expect(indicator.textContent).toBe('Save')
  })

  it('treats an error status on after:request as a failure', () => {
    fire('htmx:after:request', 500)
    expect(indicator.dataset.state).toBe('error')
    expect(indicator.textContent).toBe('Save failed')
  })

  it('handles htmx:error (network failure) and htmx:response:error', () => {
    fire('htmx:error')
    expect(indicator.dataset.state).toBe('error')

    fire('htmx:before:request')
    fire('htmx:response:error', 403)
    expect(indicator.dataset.state).toBe('error')
  })
})
