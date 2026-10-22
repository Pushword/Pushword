// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { initEmailPreview } from '../../src/Resources/assets/admin.emailPreview.js'

function pendingResponse() {
  let resolve
  const promise = new Promise((done) => { resolve = done })
  return { promise, resolve }
}

function editHeader(value) {
  const input = document.querySelector('textarea')
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
}

const response = (html) => ({ ok: true, json: async () => ({ html }) })

describe('live email design preview', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    document.body.innerHTML = `
      <div data-pw-email-preview data-viewport="mobile">
        <form action="/admin/newsletter/audience/1/email-design">
          <input type="radio" name="email_design[emailLayout]" value="simple" checked>
          <input type="radio" name="email_design[emailLayout]" value="twig:brand">
          <div data-email-editable><textarea name="email_design[headerMarkdown]"></textarea></div>
          <p data-email-template-help hidden>Defined in Twig</p>
          <input type="hidden" name="email_design[_token]" value="csrf-token">
        </form>
        <button type="button" data-preview-width="desktop" aria-pressed="false">Desktop</button>
        <button type="button" data-preview-width="mobile" aria-pressed="true">Mobile</button>
        <p role="status" data-updating="Updating" data-ready="Ready" data-failed="Failed"></p>
        <iframe sandbox="" srcdoc="Initial preview"></iframe>
      </div>`
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response('Updated preview')))
    initEmailPreview()
  })

  it('starts on mobile and lets the reader switch to desktop', () => {
    const panel = document.querySelector('[data-pw-email-preview]')
    expect(panel.dataset.viewport).toBe('mobile')
    document.querySelector('[data-preview-width="desktop"]').click()
    expect(panel.dataset.viewport).toBe('desktop')
    expect(document.querySelector('[data-preview-width="desktop"]').getAttribute('aria-pressed')).toBe('true')
    expect(document.querySelector('[data-preview-width="mobile"]').getAttribute('aria-pressed')).toBe('false')
  })

  it('keeps manual header text when switching to a Twig preset and back', async () => {
    editHeader('My header')
    const custom = document.querySelector('[value="twig:brand"]')
    custom.click()
    expect(document.querySelector('[data-email-editable]').hidden).toBe(true)
    expect(document.querySelector('[data-email-template-help]').hidden).toBe(false)
    await vi.advanceTimersByTimeAsync(250)
    expect(fetch.mock.calls[0][1].body.get('email_design[headerMarkdown]')).toBe('My header')
    document.querySelector('[value="simple"]').click()
    expect(document.querySelector('[data-email-editable]').hidden).toBe(false)
    expect(document.querySelector('textarea').value).toBe('My header')
  })

  it('can initialize a validation error page without a selected layout', () => {
    document.querySelector('[data-pw-email-preview]').removeAttribute('data-initialized')
    document.querySelector('[value="simple"]').checked = false
    expect(() => initEmailPreview()).not.toThrow()
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('debounces edits and sends the latest unsaved text with the form token', async () => {
    editHeader('First draft')
    await vi.advanceTimersByTimeAsync(100)
    editHeader('Latest draft')
    await vi.advanceTimersByTimeAsync(250)

    expect(fetch).toHaveBeenCalledTimes(1)
    const [, options] = fetch.mock.calls[0]
    expect(options.method).toBe('POST')
    expect(options.body.get('email_design[headerMarkdown]')).toBe('Latest draft')
    expect(options.body.get('email_design[_token]')).toBe('csrf-token')
    expect(options.body.get('preview')).toBe('1')
    expect(document.querySelector('iframe').srcdoc).toBe('Updated preview')
  })

  it('keeps the newest preview even if an aborted request answers last', async () => {
    const first = pendingResponse()
    const second = pendingResponse()
    fetch.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise)
    editHeader('First draft')
    await vi.advanceTimersByTimeAsync(250)
    editHeader('Latest draft')
    await vi.advanceTimersByTimeAsync(250)
    expect(fetch.mock.calls[0][1].signal.aborted).toBe(true)

    second.resolve(response('Newest preview'))
    await vi.advanceTimersByTimeAsync(0)
    first.resolve(response('Outdated preview'))
    await vi.advanceTimersByTimeAsync(0)
    expect(document.querySelector('iframe').srcdoc).toBe('Newest preview')
    expect(document.querySelector('[role="status"]').textContent).toBe('Ready')
  })

  it('keeps the last preview on failure and recovers on the next edit', async () => {
    fetch.mockResolvedValueOnce({ ok: false, status: 422 })
    editHeader('Expired token')
    await vi.advanceTimersByTimeAsync(250)
    expect(document.querySelector('iframe').srcdoc).toBe('Initial preview')
    expect(document.querySelector('[role="status"]').textContent).toBe('Failed')
    expect(document.querySelector('iframe').getAttribute('aria-busy')).toBe('false')

    editHeader('Retry')
    await vi.advanceTimersByTimeAsync(250)
    expect(document.querySelector('iframe').srcdoc).toBe('Updated preview')
    expect(document.querySelector('[role="status"]').textContent).toBe('Ready')
  })
})
