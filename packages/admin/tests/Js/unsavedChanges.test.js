// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { initUnsavedChangesRecovery } from '../../src/Resources/assets/admin.unsavedChanges.js'

const KEY = 'pw:unsaved:page:7'

const banner = () => document.getElementById('pw-unsaved-banner')
const isVisible = () => !banner().hidden
const stored = () => JSON.parse(window.localStorage.getItem(KEY))

const render = (formFields) => {
  document.body.innerHTML =
    '<div id="pw-unsaved-banner" hidden>' +
    '<span id="pw-unsaved-message"></span>' +
    '<button id="pw-unsaved-restore"></button>' +
    '<button id="pw-unsaved-discard"></button>' +
    '</div>' +
    `<form data-pw-ctrl-s-form="1" data-pw-unsaved-key="${KEY}">${formFields}</form>`

  return document.querySelector('form')
}

const type = (element, value) => {
  element.value = value
  element.dispatchEvent(new Event('input', { bubbles: true }))
}

describe('initUnsavedChangesRecovery', () => {
  let form

  beforeEach(() => {
    vi.useFakeTimers()
    window.localStorage.clear()
    form = render(
      '<input name="page[h1]" value="Server title">' +
        '<textarea name="page[mainContent]">Server body</textarea>' +
        '<input type="hidden" name="page[_token]" value="fresh-token">',
    )
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('stores the form state under the recovery key once typing settles', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Edited title')

    expect(window.localStorage.getItem(KEY)).toBeNull() // still debouncing
    vi.advanceTimersByTime(800)

    expect(stored().values['page[h1]']).toEqual(['Edited title'])
    expect(stored().values['page[mainContent]']).toEqual(['Server body'])
  })

  it('never stores the CSRF token, which the server reissues on every render', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Edited title')
    vi.advanceTimersByTime(800)

    expect(stored().values['page[_token]']).toBeUndefined()
  })

  it('drops the copy when the user undoes back to the rendered state', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Edited title')
    vi.advanceTimersByTime(800)
    expect(window.localStorage.getItem(KEY)).not.toBeNull()

    type(form.elements['page[h1]'], 'Server title')
    vi.advanceTimersByTime(800)

    expect(window.localStorage.getItem(KEY)).toBeNull()
  })

  it('offers a stored copy that differs from what the server rendered', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: { 'page[h1]': ['Kept title'], 'page[mainContent]': ['Kept body'] },
        savedAt: Date.now(),
      }),
    )
    initUnsavedChangesRecovery()

    expect(isVisible()).toBe(true)
  })

  // How stale the copy is decides whether to take it, and a wall-clock time
  // makes the editor work that out. The exact date stays reachable on hover.
  it('dates the copy relatively, keeping the timestamp on the title', () => {
    const savedAt = Date.now() - 5 * 60_000
    window.localStorage.setItem(
      KEY,
      JSON.stringify({ values: { 'page[h1]': ['Kept title'] }, savedAt }),
    )
    initUnsavedChangesRecovery()

    const message = document.getElementById('pw-unsaved-message')
    expect(message.textContent).toContain('5 minutes ago')
    expect(message.title).toBe(new Date(savedAt).toLocaleString())
  })

  it('says "this minute" rather than "0 minutes ago" for a fresh copy', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({ values: { 'page[h1]': ['Kept title'] }, savedAt: Date.now() }),
    )
    initUnsavedChangesRecovery()

    expect(document.getElementById('pw-unsaved-message').textContent).toContain(
      'this minute',
    )
  })

  it('silently forgets a copy the server state already matches', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: {
          'page[h1]': ['Server title'],
          'page[mainContent]': ['Server body'],
        },
        savedAt: Date.now(),
      }),
    )
    initUnsavedChangesRecovery()

    expect(isVisible()).toBe(false)
    expect(window.localStorage.getItem(KEY)).toBeNull()
  })

  it('restores into the form and keeps the copy, since it is still unsaved', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: { 'page[h1]': ['Kept title'], 'page[mainContent]': ['Kept body'] },
        savedAt: Date.now(),
      }),
    )
    initUnsavedChangesRecovery()
    document.getElementById('pw-unsaved-restore').click()

    expect(form.elements['page[h1]'].value).toBe('Kept title')
    expect(form.elements['page[mainContent]'].value).toBe('Kept body')
    vi.advanceTimersByTime(150) // the dismissal fade
    expect(isVisible()).toBe(false)
    expect(window.localStorage.getItem(KEY)).not.toBeNull()
  })

  it('discards the copy on demand', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({ values: { 'page[h1]': ['Kept title'] }, savedAt: Date.now() }),
    )
    initUnsavedChangesRecovery()
    document.getElementById('pw-unsaved-discard').click()

    vi.advanceTimersByTime(150) // the dismissal fade
    expect(isVisible()).toBe(false)
    expect(window.localStorage.getItem(KEY)).toBeNull()
    expect(form.elements['page[h1]'].value).toBe('Server title') // untouched
  })

  it('clears the copy once a Ctrl+S save succeeds', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Edited title')
    vi.advanceTimersByTime(800)

    form.dispatchEvent(
      new CustomEvent('htmx:after:request', {
        detail: { ctx: { response: { status: 200 } } },
      }),
    )

    expect(window.localStorage.getItem(KEY)).toBeNull()
  })

  it('keeps the copy when the save failed', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Edited title')
    vi.advanceTimersByTime(800)

    form.dispatchEvent(
      new CustomEvent('htmx:after:request', {
        detail: { ctx: { response: { status: 500 } } },
      }),
    )

    expect(window.localStorage.getItem(KEY)).not.toBeNull()
  })

  it('does nothing on a form without a recovery key (a page being created)', () => {
    document.body.innerHTML = '<form data-pw-ctrl-s-form="1"></form>'

    expect(initUnsavedChangesRecovery()).toBeNull()
  })

  // EasyMDE and EditorJS both render beside the field they feed, and both write
  // it without firing an event, so both expose this seam.
  it('writes through the editor seam so a rich editor updates too', () => {
    const setValue = vi.fn()
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: { 'page[mainContent]': ['Recovered body'] },
        savedAt: Date.now(),
      }),
    )
    form.elements['page[mainContent]'].pwEditor = { setValue }

    initUnsavedChangesRecovery()
    document.getElementById('pw-unsaved-restore').click()

    expect(setValue).toHaveBeenCalledWith('Recovered body')
    // The field is left to the editor, which syncs it back on its own.
    expect(form.elements['page[mainContent]'].value).toBe('Server body')
  })
})

describe('initUnsavedChangesRecovery on checkboxes and multi-selects', () => {
  let form

  beforeEach(() => {
    vi.useFakeTimers()
    window.localStorage.clear()
    form = render(
      '<input type="checkbox" name="page[markdown]" value="1" checked>' +
        '<select name="page[tags][]" multiple>' +
        '<option value="a" selected>a</option><option value="b">b</option>' +
        '</select>',
    )
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('records an unchecked box as an empty entry', () => {
    initUnsavedChangesRecovery()
    const checkbox = form.elements['page[markdown]']
    checkbox.checked = false
    checkbox.dispatchEvent(new Event('change', { bubbles: true }))
    vi.advanceTimersByTime(800)

    expect(stored().values['page[markdown]']).toEqual([])
  })

  it('unchecks a box the copy recorded as empty', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({ values: { 'page[markdown]': [] }, savedAt: Date.now() }),
    )
    initUnsavedChangesRecovery()
    document.getElementById('pw-unsaved-restore').click()

    expect(form.elements['page[markdown]'].checked).toBe(false)
  })

  it('round-trips a multi-select', () => {
    initUnsavedChangesRecovery()
    const select = form.elements['page[tags][]']
    select.options[1].selected = true
    select.dispatchEvent(new Event('change', { bubbles: true }))
    vi.advanceTimersByTime(800)

    expect(stored().values['page[tags][]']).toEqual(['a', 'b'])
  })
})
