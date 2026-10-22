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
      <div data-pw-email-preview>
        <form action="/admin/newsletter/audience/1/email-design">
          <textarea name="email_design[headerMarkdown]"></textarea>
          <input type="hidden" name="email_design[_token]" value="csrf-token">
        </form>
        <p role="status" data-updating="Updating" data-ready="Ready" data-failed="Failed"></p>
        <iframe sandbox="" srcdoc="Initial preview"></iframe>
      </div>`
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response('Updated preview')))
    initEmailPreview()
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
