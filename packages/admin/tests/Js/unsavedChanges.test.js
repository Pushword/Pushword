// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  initUnsavedChangesRecovery,
  initUnsavedChangesSignOutClear,
} from '../../src/Resources/assets/admin.unsavedChanges.js'

const KEY = 'pw:unsaved:3:page:7'

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

  it.each([
    ['2 hours ago', 2 * 60 * 60_000],
    ['yesterday', 24 * 60 * 60_000],
    ['3 days ago', 3 * 24 * 60 * 60_000],
  ])('scales the unit up to %s', (expected, elapsed) => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: { 'page[h1]': ['Kept title'] },
        savedAt: Date.now() - elapsed,
      }),
    )
    initUnsavedChangesRecovery()

    expect(document.getElementById('pw-unsaved-message').textContent).toContain(expected)
  })

  // A submit is a request, not a save: an expired session has the firewall
  // redirect the POST and discard its body, and that is the case this recovery
  // exists for. So the copy outlives the submit, and the next load decides.
  const submitAndReopenOn = (serverTitle) => {
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))

    form = render(
      `<input name="page[h1]" value="${serverTitle}">` +
        '<textarea name="page[mainContent]">Server body</textarea>' +
        '<input type="hidden" name="page[_token]" value="fresh-token">',
    )
    initUnsavedChangesRecovery()
  }

  it('keeps the copy when the submit never reached the database', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Edited title')
    vi.advanceTimersByTime(800)

    // The edit form comes back rendered from the database, unchanged: the POST
    // was lost on the way.
    submitAndReopenOn('Server title')

    expect(isVisible()).toBe(true)
    expect(stored().values['page[h1]']).toEqual(['Edited title'])
  })

  it('retires the copy once the server renders what was submitted', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Edited title')
    vi.advanceTimersByTime(800)

    submitAndReopenOn('Edited title')

    expect(isVisible()).toBe(false)
    expect(window.localStorage.getItem(KEY)).toBeNull()
  })

  // After a save the form on screen IS the server state, so the comparison has
  // to move with it: otherwise editing again would be measured against what the
  // page was rendered with, and undoing back to it would wrongly drop the copy.
  it('re-baselines on save, so later edits are still captured', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Saved title')
    vi.advanceTimersByTime(800)

    form.dispatchEvent(
      new CustomEvent('htmx:after:request', {
        detail: { ctx: { response: { status: 200 } } },
      }),
    )
    expect(window.localStorage.getItem(KEY)).toBeNull()

    type(form.elements['page[h1]'], 'Saved title, edited further')
    vi.advanceTimersByTime(800)
    expect(stored().values['page[h1]']).toEqual(['Saved title, edited further'])

    // Back to what was saved: nothing left to recover.
    type(form.elements['page[h1]'], 'Saved title')
    vi.advanceTimersByTime(800)
    expect(window.localStorage.getItem(KEY)).toBeNull()
  })

  it('coalesces a burst of typing into one write', () => {
    initUnsavedChangesRecovery()
    const writes = vi.spyOn(Storage.prototype, 'setItem')

    for (const value of ['E', 'Ed', 'Edi', 'Edit']) {
      type(form.elements['page[h1]'], value)
      vi.advanceTimersByTime(200) // under the debounce
    }
    vi.advanceTimersByTime(800)

    expect(writes).toHaveBeenCalledOnce()
    writes.mockRestore()
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
    document.body.innerHTML =
      '<form data-pw-ctrl-s-form="1"><input name="page[h1]" value="A"></form>'
    const created = document.querySelector('form')
    initUnsavedChangesRecovery()

    type(created.elements['page[h1]'], 'Typed while creating')
    vi.advanceTimersByTime(800)

    expect(window.localStorage.length).toBe(0)
  })

  // Monaco and EditorJS both render beside the field they feed, and both write
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

describe('initUnsavedChangesRecovery on radio groups', () => {
  let form

  beforeEach(() => {
    vi.useFakeTimers()
    window.localStorage.clear()
    form = render(
      '<input name="page[h1]" value="Server title">' +
        '<input type="radio" name="page[metaRobots]" value="index" checked>' +
        '<input type="radio" name="page[metaRobots]" value="noindex">',
    )
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  // Same name across several controls: serialize must keep the checked one, not
  // simply the last one it walked past. Asserting the *unchanged* group is what
  // separates the two, since the last radio here is the unchecked one.
  it('keeps the selected radio, not the last of the group', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Edited title')
    vi.advanceTimersByTime(800)

    expect(stored().values['page[metaRobots]']).toEqual(['index'])
  })

  it('follows the selection when it moves', () => {
    initUnsavedChangesRecovery()
    const [, noindex] = form.elements['page[metaRobots]']
    noindex.checked = true
    noindex.dispatchEvent(new Event('change', { bubbles: true }))
    vi.advanceTimersByTime(800)

    expect(stored().values['page[metaRobots]']).toEqual(['noindex'])
  })

  it('moves the selection back on restore', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: { 'page[metaRobots]': ['noindex'] },
        savedAt: Date.now(),
      }),
    )
    initUnsavedChangesRecovery()
    document.getElementById('pw-unsaved-restore').click()

    const [index, noindex] = form.elements['page[metaRobots]']
    expect(noindex.checked).toBe(true)
    expect(index.checked).toBe(false)
  })
})

describe('initUnsavedChangesRecovery on fields it must leave alone', () => {
  let form

  beforeEach(() => {
    vi.useFakeTimers()
    window.localStorage.clear()
    form = render(
      '<input name="page[h1]" value="Server title">' +
        '<input type="file" name="page[mediaFile]">' +
        '<input name="page[locked]" value="Read only" disabled>',
    )
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  // A file input's value cannot be set back for security reasons, and a
  // disabled field is not the user's to restore.
  it('stores neither file inputs nor disabled fields', () => {
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'Edited title')
    vi.advanceTimersByTime(800)

    expect(Object.keys(stored().values)).toEqual(['page[h1]'])
  })

  it('leaves a field the stored copy never mentioned', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({ values: { 'page[absent]': ['x'] }, savedAt: Date.now() }),
    )
    initUnsavedChangesRecovery()
    document.getElementById('pw-unsaved-restore').click()

    expect(form.elements['page[h1]'].value).toBe('Server title')
  })
})

describe('initUnsavedChangesRecovery when storage misbehaves', () => {
  let form

  beforeEach(() => {
    vi.useFakeTimers()
    window.localStorage.clear()
    form = render('<input name="page[h1]" value="Server title">')
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  // Recovery is a bonus. A browser with storage disabled, or a quota already
  // full, must not take the edit form down with it.
  it('keeps typing working when the write is refused', () => {
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('quota', 'QuotaExceededError')
    })
    initUnsavedChangesRecovery()

    expect(() => {
      type(form.elements['page[h1]'], 'Edited title')
      vi.advanceTimersByTime(800)
    }).not.toThrow()
  })

  it('ignores an unparsable stored value instead of throwing', () => {
    window.localStorage.setItem(KEY, 'not json {')

    expect(() => initUnsavedChangesRecovery()).not.toThrow()
    expect(isVisible()).toBe(false)
  })

  it('overwrites that unparsable value on the next change', () => {
    window.localStorage.setItem(KEY, 'not json {')
    initUnsavedChangesRecovery()

    type(form.elements['page[h1]'], 'Edited title')
    vi.advanceTimersByTime(800)

    expect(stored().values['page[h1]']).toEqual(['Edited title'])
  })
})

/**
 * A copy is what one editor changed, not a picture of the page: everything else
 * in it is only the form as it was rendered that day. Writing all of it back
 * would revert whatever was saved meanwhile, silently and without a diff.
 */
describe('initUnsavedChangesRecovery against a page saved meanwhile', () => {
  const draft = (values, was) =>
    window.localStorage.setItem(
      KEY,
      JSON.stringify({ values, baseline: was, savedAt: Date.now() }),
    )

  /** The edit screen as the server renders it now, someone else's save included. */
  const reopenOn = (title, body) =>
    render(
      `<input name="page[h1]" value="${title}">` +
        `<textarea name="page[mainContent]">${body}</textarea>`,
    )

  beforeEach(() => {
    vi.useFakeTimers()
    window.localStorage.clear()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('writes back the field the copy changed and leaves the rest as saved', () => {
    draft(
      { 'page[h1]': ['My title'], 'page[mainContent]': ['Body as it was'] },
      { 'page[h1]': ['Server title'], 'page[mainContent]': ['Body as it was'] },
    )
    const form = reopenOn('Server title', 'Body a colleague saved')
    initUnsavedChangesRecovery()
    document.getElementById('pw-unsaved-restore').click()

    expect(form.elements['page[h1]'].value).toBe('My title')
    expect(form.elements['page[mainContent]'].value).toBe('Body a colleague saved')
  })

  it('says so when the page moved under a field the copy would write over', () => {
    draft(
      { 'page[h1]': ['My title'], 'page[mainContent]': ['Server body'] },
      { 'page[h1]': ['Server title'], 'page[mainContent]': ['Server body'] },
    )
    reopenOn('A title a colleague saved', 'Server body')
    initUnsavedChangesRecovery()

    expect(document.getElementById('pw-unsaved-message').textContent).toContain(
      'has been saved since',
    )
    expect(banner().classList.contains('pw-unsaved--conflict')).toBe(true)
  })

  // The page moved, but on a field this copy leaves alone: restoring overwrites
  // nothing, so the offer stays the plain one.
  it('keeps the plain offer when the copy and the save touch different fields', () => {
    draft(
      { 'page[h1]': ['My title'], 'page[mainContent]': ['Server body'] },
      { 'page[h1]': ['Server title'], 'page[mainContent]': ['Server body'] },
    )
    reopenOn('Server title', 'Body a colleague saved')
    initUnsavedChangesRecovery()

    expect(document.getElementById('pw-unsaved-message').textContent).not.toContain(
      'has been saved since',
    )
    expect(banner().classList.contains('pw-unsaved--conflict')).toBe(false)
  })

  // Nothing left to offer: the field this copy changed now holds that very value.
  it('drops a copy the page has caught up with, field by field', () => {
    draft(
      { 'page[h1]': ['My title'], 'page[mainContent]': ['Body as it was'] },
      { 'page[h1]': ['Server title'], 'page[mainContent]': ['Body as it was'] },
    )
    reopenOn('My title', 'Body a colleague saved')
    initUnsavedChangesRecovery()

    expect(isVisible()).toBe(false)
    expect(window.localStorage.getItem(KEY)).toBeNull()
  })

  // A copy written before the baseline was recorded knows nothing of what the
  // page held, so it cannot claim the page moved — and it restores whole, the
  // way it always did.
  it('says nothing about a page it never measured', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: { 'page[h1]': ['My title'], 'page[mainContent]': ['Body as it was'] },
        savedAt: Date.now(),
      }),
    )
    const form = reopenOn('A title a colleague saved', 'Body a colleague saved')
    initUnsavedChangesRecovery()

    expect(banner().classList.contains('pw-unsaved--conflict')).toBe(false)

    document.getElementById('pw-unsaved-restore').click()

    expect(form.elements['page[mainContent]'].value).toBe('Body as it was')
  })

  it('stores the form as the server rendered it alongside the changes', () => {
    const form = reopenOn('Server title', 'Server body')
    initUnsavedChangesRecovery()
    type(form.elements['page[h1]'], 'My title')
    vi.advanceTimersByTime(800)

    expect(stored().baseline['page[h1]']).toEqual(['Server title'])
  })
})

/**
 * A rich editor normalises the markdown it is handed and writes the result back
 * into the field it feeds — after this module runs, and looking exactly like
 * typing. Baselining before that lands books the normalisation as an edit: every
 * open of an untouched page would store a copy nobody typed, over the real one.
 */
describe('initUnsavedChangesRecovery while a rich editor is still parsing', () => {
  let form
  let body

  const renderPending = () => {
    form = render(
      '<input name="page[h1]" value="Server title">' +
        '<textarea name="page[mainContent]" data-pw-baseline-pending="1">Server body</textarea>',
    )
    body = form.elements['page[mainContent]']
  }

  /** What the parse does: rewrite the field with its own markdown, and say so. */
  const parseSettles = (normalised) => {
    type(body, normalised)
    body.removeAttribute('data-pw-baseline-pending')
    body.dispatchEvent(new CustomEvent('pw:baseline-ready', { bubbles: true }))
  }

  beforeEach(() => {
    vi.useFakeTimers()
    window.localStorage.clear()
    renderPending()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('stores nothing for a page nobody typed in', () => {
    initUnsavedChangesRecovery()
    parseSettles('Server body\n')
    vi.advanceTimersByTime(800)

    expect(window.localStorage.getItem(KEY)).toBeNull()
  })

  it('takes the parsed markdown as the baseline, so real typing still lands', () => {
    initUnsavedChangesRecovery()
    parseSettles('Server body\n')
    vi.advanceTimersByTime(800)

    type(body, 'Typed by hand')
    vi.advanceTimersByTime(800)

    expect(stored().values['page[mainContent]']).toEqual(['Typed by hand'])
    expect(stored().baseline['page[mainContent]']).toEqual(['Server body\n'])
  })

  it('leaves a stored copy alone until it knows what the page holds', () => {
    window.localStorage.setItem(
      KEY,
      JSON.stringify({
        values: { 'page[mainContent]': ['Kept body'] },
        baseline: { 'page[mainContent]': ['Server body\n'] },
        savedAt: Date.now(),
      }),
    )
    initUnsavedChangesRecovery()

    // The parse alone must neither overwrite the copy nor retire it.
    type(body, 'Server body\n')
    vi.advanceTimersByTime(800)
    expect(stored().values['page[mainContent]']).toEqual(['Kept body'])
    expect(isVisible()).toBe(false)

    body.removeAttribute('data-pw-baseline-pending')
    body.dispatchEvent(new CustomEvent('pw:baseline-ready', { bubbles: true }))

    expect(isVisible()).toBe(true)
  })
})

describe('initUnsavedChangesSignOutClear', () => {
  beforeEach(() => {
    window.localStorage.clear()
    window.pwLogoutPath = '/logout'
    // The sign-out item EasyAdmin renders is an icon and a label inside the
    // link, so the click lands on a child; the last two are near misses.
    document.body.innerHTML =
      '<a id="out" href="/logout"><svg></svg><span id="label">Sign out</span></a>' +
      `<a id="absolute" href="${window.location.origin}/logout?redirect=/admin">Sign out</a>` +
      '<a id="lookalike" href="/admin/logout">Log of the outbox</a>' +
      '<a id="stay" href="/admin?page=2">Pages</a>'
    // jsdom cannot navigate, and the error it logs for the attempt is noise here.
    for (const link of document.querySelectorAll('a'))
      link.addEventListener('click', (event) => event.preventDefault())

    initUnsavedChangesSignOutClear()
  })

  afterEach(() => {
    delete window.pwLogoutPath
  })

  // localStorage is per browser and outlives the session: left behind, the copy
  // would be offered to — and restorable by — whoever signs in next.
  it('takes every draft in this browser along', () => {
    window.localStorage.setItem('pw:unsaved:3:page:7', '{}')
    window.localStorage.setItem('pw:unsaved:9:page:1', '{}')
    window.localStorage.setItem('pw-edit-lock-tab-id', 'not ours to drop')

    document.getElementById('label').click()

    expect(window.localStorage.getItem('pw:unsaved:3:page:7')).toBeNull()
    expect(window.localStorage.getItem('pw:unsaved:9:page:1')).toBeNull()
    expect(window.localStorage.getItem('pw-edit-lock-tab-id')).toBe('not ours to drop')
  })

  it('recognises the way out written in full, query string and all', () => {
    window.localStorage.setItem(KEY, '{}')

    document.getElementById('absolute').click()

    expect(window.localStorage.getItem(KEY)).toBeNull()
  })

  // Only signing out is deliberate. A session that expires on its own is the
  // case recovery exists for, and navigating is not leaving.
  it.each([
    ['another admin screen', 'stay'],
    ['a path that merely ends the same way', 'lookalike'],
  ])('keeps them on %s', (_, id) => {
    window.localStorage.setItem(KEY, '{}')

    document.getElementById(id).click()

    expect(window.localStorage.getItem(KEY)).toBe('{}')
  })

  // A browser refusing storage must not take the link down with it.
  it('lets the editor out even when the browser refuses storage', () => {
    vi.spyOn(Storage.prototype, 'removeItem').mockImplementation(() => {
      throw new DOMException('denied', 'SecurityError')
    })
    window.localStorage.setItem(KEY, '{}')

    expect(() => document.getElementById('label').click()).not.toThrow()
    vi.restoreAllMocks()
  })
})
