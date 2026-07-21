import { describe, it, expect, vi, beforeEach } from 'vitest'
import { liveBlock, addClassForNormalUser } from './helpers.js'

// Helpers to build minimal DOM fixtures
function makeLiveBlockEl(url) {
  const el = document.createElement('div')
  el.setAttribute('data-live', url)
  document.body.appendChild(el)
  return el
}

function makeLiveFormBlock(action) {
  const block = document.createElement('div')
  block.className = 'live-form'
  const form = document.createElement('form')
  form.action = action
  const input = document.createElement('input')
  input.name = 'field'
  input.value = 'val'
  form.appendChild(input)
  block.appendChild(form)
  document.body.appendChild(block)
  return { block, form }
}

describe('liveBlock — getLiveBlock', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    document.cookie = 'pw_auth=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
    vi.restoreAllMocks()
  })

  it('replaces outerHTML and dispatches DOMChanged on 200', async () => {
    makeLiveBlockEl('/block')
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        text: () => Promise.resolve('<div>new content</div>'),
      }),
    )
    const domChanged = vi.fn()
    document.addEventListener('DOMChanged', domChanged)

    liveBlock()

    await vi.waitFor(() => expect(domChanged).toHaveBeenCalledOnce())
    expect(document.body.innerHTML).toContain('new content')
    expect(document.body.querySelector('[data-live]')).toBeNull()

    document.removeEventListener('DOMChanged', domChanged)
  })

  it('does not replace outerHTML on 403 and fires live-block-forbidden', async () => {
    const el = makeLiveBlockEl('/block')
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 403,
      text: () => Promise.resolve('<html>login page</html>'),
    })
    vi.stubGlobal('fetch', fetchMock)

    let forbiddenDetail = null
    document.body.addEventListener('live-block-forbidden', (e) => {
      forbiddenDetail = e.detail
    })

    liveBlock()

    await vi.waitFor(() => expect(forbiddenDetail).not.toBeNull())
    expect(forbiddenDetail.status).toBe(403)
    expect(forbiddenDetail.url).toBe('/block')
    // original block must still be present, but without its fetch trigger:
    // liveBlock() re-runs on every DOMChanged and must not retry a failed block
    expect(document.body.contains(el)).toBe(true)
    expect(el.hasAttribute('data-live')).toBe(false)
    expect(document.body.innerHTML).not.toContain('login page')

    liveBlock()
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('skips a data-live-if cookie-gated block when the cookie is absent', () => {
    const el = makeLiveBlockEl('/admin/fragment/page-buttons/1')
    el.setAttribute('data-live-if', 'cookie:pw_auth=1')
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    liveBlock()

    expect(fetchMock).not.toHaveBeenCalled()
    // the trigger stays: the gate is re-evaluated on the next liveBlock() run
    expect(el.hasAttribute('data-live')).toBe(true)
  })

  it('fetches a data-live-if cookie-gated block when the cookie matches', async () => {
    const el = makeLiveBlockEl('/admin/fragment/page-buttons/1')
    el.setAttribute('data-live-if', 'cookie:pw_auth=1')
    document.cookie = 'pw_auth=1'
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      text: () => Promise.resolve('<div>toolbar</div>'),
    })
    vi.stubGlobal('fetch', fetchMock)

    liveBlock()

    await vi.waitFor(() => expect(document.body.innerHTML).toContain('toolbar'))
    expect(fetchMock).toHaveBeenCalledOnce()

    document.cookie = 'pw_auth=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
  })
})

describe('addClassForNormalUser — one-time hash navigation', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    window.location.hash = '#quiz'
    vi.restoreAllMocks()
  })

  function scrollFourTimes() {
    for (let i = 0; i < 4; i++) document.dispatchEvent(new Event('scroll'))
  }

  it('applies location.hash navigation at most once across re-inits', () => {
    const scrollToHash = vi.fn()
    window.ShowMore = { scrollToHash }

    // Initial page load registers the watcher; its 4th scroll event applies
    // the one-time hash correction.
    addClassForNormalUser()
    scrollFourTimes()
    expect(scrollToHash).toHaveBeenCalledTimes(1)
    expect(scrollToHash).toHaveBeenCalledWith('#quiz')

    // A later DOMChanged (e.g. a quiz revealing its result box) re-registers
    // the watcher; the ensuing programmatic-scroll burst must NOT yank the
    // user back to the anchor.
    addClassForNormalUser()
    scrollFourTimes()
    expect(scrollToHash).toHaveBeenCalledTimes(1)
  })
})

describe('liveBlock — sendForm', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    vi.restoreAllMocks()
  })

  it('replaces outerHTML and dispatches DOMChanged on 200', async () => {
    const { block } = makeLiveFormBlock('/submit')
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        text: () => Promise.resolve('<div>thank you</div>'),
      }),
    )
    const domChanged = vi.fn()
    document.addEventListener('DOMChanged', domChanged)

    liveBlock()
    block.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true }))

    await vi.waitFor(() => expect(domChanged).toHaveBeenCalledOnce())
    expect(document.body.innerHTML).toContain('thank you')

    document.removeEventListener('DOMChanged', domChanged)
  })

  it('does not replace outerHTML on 403, fires live-block-forbidden, clears data-submitting', async () => {
    const { block, form } = makeLiveFormBlock('/submit')
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 403,
        text: () => Promise.resolve('<html>login</html>'),
      }),
    )

    let forbiddenDetail = null
    document.body.addEventListener('live-block-forbidden', (e) => {
      forbiddenDetail = e.detail
    })

    liveBlock()
    form.dispatchEvent(new Event('submit', { bubbles: true }))

    await vi.waitFor(() => expect(forbiddenDetail).not.toBeNull())
    expect(forbiddenDetail.status).toBe(403)
    expect(document.body.querySelector('.live-form')).not.toBeNull()
    expect(document.body.innerHTML).not.toContain('login')
    // data-submitting must be cleared so the form is retryable
    expect(block.dataset.submitting).toBeUndefined()
  })
})
