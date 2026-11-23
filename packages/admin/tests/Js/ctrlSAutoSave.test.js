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

  it('Ctrl+S saves even when an editor control stops the event at window capture', () => {
    const triggered = vi.fn()
    form.addEventListener('pw-ctrl-s-event', triggered)
    window.addEventListener('keydown', (event) => event.stopPropagation(), { capture: true, once: true })
    form.dispatchEvent(
      new KeyboardEvent('keydown', { key: 's', ctrlKey: true, cancelable: true }),
    )
    expect(triggered).toHaveBeenCalledOnce()
  })

  it('leaves native submissions alone on forms without a shortcut button', () => {
    const triggered = vi.fn()
    form.addEventListener('pw-ctrl-s-event', triggered)
    const manual = new SubmitEvent('submit', { cancelable: true })
    expect(form.dispatchEvent(manual)).toBe(true)
    expect(triggered).not.toHaveBeenCalled()
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

it('runs the guarded shortcut through the form and confirms only a revision-bearing save', () => {
  vi.useFakeTimers()
  vi.stubGlobal('htmx', {})
  try {
    document.body.innerHTML =
      '<span id="ind">Save</span>' +
      '<form data-pw-ctrl-s-form="1" data-pw-ctrl-s-indicator="#ind">' +
      '<input type="hidden" data-pw-page-revision>' +
      '<button type="submit" data-pw-ctrl-s-submit hidden></button></form>'
    const form = document.querySelector('form')
    const submitted = vi.fn()
    const triggered = vi.fn()
    form.addEventListener('submit', submitted)
    form.addEventListener('pw-ctrl-s-event', triggered)
    initCtrlSAutoSave()

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 's', ctrlKey: true, cancelable: true }))
    expect(submitted.mock.calls[0][0].submitter).toBe(form.querySelector('button'))
    expect(triggered).toHaveBeenCalledOnce()

    const complete = (response) => form.dispatchEvent(new CustomEvent('htmx:after:request', { detail: { ctx: { response } } }))
    complete({status: 200, headers: new Headers()})
    expect(document.querySelector('#ind').textContent).toBe('Save failed')
    complete({status: 204, headers: new Headers({'X-Pushword-Page-Revision': '4'})})
    expect(document.querySelector('#ind').textContent).toBe('Saved')
  } finally {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  }
})

it('cancels shortcut submission before EasyAdmin can disable the save button', () => {
  vi.stubGlobal('htmx', {})
  try {
    document.body.innerHTML = `
      <form data-pw-ctrl-s-form="1">
        <button type="submit">Save</button>
        <button type="submit" data-pw-ctrl-s-submit hidden></button>
      </form>`
    const form = document.querySelector('form')
    const [save, shortcut] = form.querySelectorAll('button')
    const autosave = vi.fn()
    form.addEventListener('submit', (event) => {
      if (!event.defaultPrevented) save.disabled = true
    })
    form.addEventListener('submit', (event) => event.preventDefault(), { capture: true, once: true })
    form.addEventListener('pw-ctrl-s-event', autosave)
    initCtrlSAutoSave()

    form.requestSubmit(shortcut)
    expect(autosave).not.toHaveBeenCalled()
    expect(save.disabled).toBe(false)

    const prepareEditor = (event) => event.stopImmediatePropagation()
    form.addEventListener('submit', prepareEditor, { capture: true, once: true })
    form.requestSubmit(shortcut)
    expect(autosave).not.toHaveBeenCalled()
    expect(save.disabled).toBe(false)

    form.requestSubmit(shortcut)
    expect(autosave).toHaveBeenCalledOnce()
    expect(save.disabled).toBe(false)

    const manual = new SubmitEvent('submit', { submitter: save, cancelable: true })
    expect(form.dispatchEvent(manual)).toBe(true)
    expect(save.disabled).toBe(true)
    expect(autosave).toHaveBeenCalledOnce()
  } finally {
    vi.unstubAllGlobals()
  }
})
