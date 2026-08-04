// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { initUnsavedChangesRecovery } from '../../src/Resources/assets/admin.unsavedChanges.js'

const KEY = 'pw:unsaved:page:7'

const banner = () => document.getElementById('pw-unsaved-banner')
const isVisible = () => banner().style.display === 'flex'
const stored = () => JSON.parse(window.localStorage.getItem(KEY))

const render = (formFields) => {
  document.body.innerHTML =
    '<div id="pw-unsaved-banner" style="display: none" aria-hidden="true">' +
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

  it('stores the form state under the draft key once typing settles', () => {
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

  it('drops the draft when the user undoes back to the rendered state', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Edited title')
    vi.advanceTimersByTime(800)
    expect(window.localStorage.getItem(KEY)).not.toBeNull()

    type(form.elements['page[h1]'], 'Server title')
    vi.advanceTimersByTime(800)

    expect(window.localStorage.getItem(KEY)).toBeNull()
  })

  it('offers a stored draft that differs from what the server rendered', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: { 'page[h1]': ['Draft title'], 'page[mainContent]': ['Draft body'] },
        savedAt: Date.parse('2026-08-04T14:32:00Z'),
      }),
    )
    initUnsavedChangesRecovery()

    expect(isVisible()).toBe(true)
    expect(banner().getAttribute('aria-hidden')).toBe('false')
    expect(document.getElementById('pw-unsaved-message').textContent).toContain(
      new Date(Date.parse('2026-08-04T14:32:00Z')).toLocaleString(),
    )
  })

  it('silently forgets a draft the server state already matches', () => {
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

  it('restores the draft into the form and keeps it stored, since it is still unsaved', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: { 'page[h1]': ['Draft title'], 'page[mainContent]': ['Draft body'] },
        savedAt: Date.now(),
      }),
    )
    initUnsavedChangesRecovery()
    document.getElementById('pw-unsaved-restore').click()

    expect(form.elements['page[h1]'].value).toBe('Draft title')
    expect(form.elements['page[mainContent]'].value).toBe('Draft body')
    expect(isVisible()).toBe(false)
    expect(window.localStorage.getItem(KEY)).not.toBeNull()
  })

  it('discards the draft on demand', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({ values: { 'page[h1]': ['Draft title'] }, savedAt: Date.now() }),
    )
    initUnsavedChangesRecovery()
    document.getElementById('pw-unsaved-discard').click()

    expect(isVisible()).toBe(false)
    expect(window.localStorage.getItem(KEY)).toBeNull()
    expect(form.elements['page[h1]'].value).toBe('Server title') // untouched
  })

  it('clears the draft once a Ctrl+S save succeeds', () => {
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

  it('keeps the draft when the save failed', () => {
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

  it('does nothing on a form without a draft key (a page being created)', () => {
    document.body.innerHTML = '<form data-pw-ctrl-s-form="1"></form>'

    expect(initUnsavedChangesRecovery()).toBeNull()
  })

  it('writes through the EasyMDE instance so the visible editor updates too', () => {
    const value = vi.fn()
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: { 'page[mainContent]': ['Draft body'] },
        savedAt: Date.now(),
      }),
    )
    form.elements['page[mainContent]'].easyMDE = { value }

    initUnsavedChangesRecovery()
    document.getElementById('pw-unsaved-restore').click()

    expect(value).toHaveBeenCalledWith('Draft body')
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

  it('unchecks a box the draft recorded as empty', () => {
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
