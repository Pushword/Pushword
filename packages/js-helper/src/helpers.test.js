import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import {
  liveBlock,
  addClassForNormalUser,
  resolveLightboxSources,
  uncloakLinks,
} from './helpers.js'

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

describe('liveBlock — gate registry', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    vi.restoreAllMocks()
  })

  it('fails closed on an unknown gate prefix', () => {
    const el = makeLiveBlockEl('/block')
    el.setAttribute('data-live-if', 'weird:thing')
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    liveBlock()

    expect(fetchMock).not.toHaveBeenCalled()
    expect(el.hasAttribute('data-live')).toBe(true)
  })

  it('skips a media-gated block when the query does not match, and watches it', () => {
    const el = makeLiveBlockEl('/block')
    el.setAttribute('data-live-if', 'media:(min-width: 100px)')
    const addListener = vi.fn()
    vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: false, addEventListener: addListener })))
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    liveBlock()

    expect(fetchMock).not.toHaveBeenCalled()
    expect(el.hasAttribute('data-live')).toBe(true)
    expect(addListener).toHaveBeenCalledWith('change', expect.any(Function))
  })

  it('fetches a media-gated block when the query matches', async () => {
    makeLiveBlockEl('/block').setAttribute('data-live-if', 'media:(min-width: 200px)')
    vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: true, addEventListener: vi.fn() })))
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({ ok: true, text: () => Promise.resolve('<div>wide</div>') }),
    )

    liveBlock()

    await vi.waitFor(() => expect(document.body.innerHTML).toContain('wide'))
  })

  it('re-dispatches DOMChanged when a watched media query flips', () => {
    makeLiveBlockEl('/block').setAttribute('data-live-if', 'media:(min-width: 300px)')
    let changeHandler = null
    vi.stubGlobal(
      'matchMedia',
      vi.fn(() => ({ matches: false, addEventListener: (_, fn) => (changeHandler = fn) })),
    )
    vi.stubGlobal('fetch', vi.fn())

    liveBlock()
    const domChanged = vi.fn()
    document.addEventListener('DOMChanged', domChanged)
    changeHandler()
    expect(domChanged).toHaveBeenCalledOnce()
    document.removeEventListener('DOMChanged', domChanged)
  })
})

describe('liveBlock — data-live-trigger', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    vi.restoreAllMocks()
  })

  it('defers the fetch to the window event and fires once by default', async () => {
    makeLiveBlockEl('/deferred').setAttribute('data-live-trigger', 'open-once')
    const fetchMock = vi
      .fn()
      .mockResolvedValue({ ok: true, text: () => Promise.resolve('<div>opened</div>') })
    vi.stubGlobal('fetch', fetchMock)

    liveBlock()
    expect(fetchMock).not.toHaveBeenCalled()

    // a second scan pass must not double-bind
    liveBlock()
    window.dispatchEvent(new Event('open-once'))
    await vi.waitFor(() => expect(document.body.innerHTML).toContain('opened'))
    expect(fetchMock).toHaveBeenCalledOnce()

    window.dispatchEvent(new Event('open-once'))
    expect(fetchMock).toHaveBeenCalledOnce()
  })

  it('refetches on every event into a surviving container with data-live-repeat', async () => {
    const el = makeLiveBlockEl('/fresh')
    el.setAttribute('data-live-trigger', 'open-repeat')
    el.setAttribute('data-live-repeat', '')
    const fetchMock = vi
      .fn()
      .mockResolvedValue({ ok: true, text: () => Promise.resolve('<form>fresh</form>') })
    vi.stubGlobal('fetch', fetchMock)

    liveBlock()
    window.dispatchEvent(new Event('open-repeat'))
    await vi.waitFor(() => expect(el.innerHTML).toContain('fresh'))
    expect(document.body.contains(el)).toBe(true)
    expect(el.hasAttribute('data-live')).toBe(true)

    window.dispatchEvent(new Event('open-repeat'))
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2))
  })

  it('disarms a once trigger even when the fetch fails', async () => {
    const el = makeLiveBlockEl('/once-fail')
    el.setAttribute('data-live-trigger', 'open-once-fail')
    const fetchMock = vi
      .fn()
      .mockResolvedValue({ ok: false, status: 403, text: () => Promise.resolve('nope') })
    vi.stubGlobal('fetch', fetchMock)
    let forbidden = null
    document.body.addEventListener('live-block-forbidden', (e) => (forbidden = e.detail))

    liveBlock()
    window.dispatchEvent(new Event('open-once-fail'))
    await vi.waitFor(() => expect(forbidden).not.toBeNull())
    // the trigger is stripped (no-retry rule) and the listener disarmed
    expect(el.hasAttribute('data-live')).toBe(false)
    window.dispatchEvent(new Event('open-once-fail'))
    expect(fetchMock).toHaveBeenCalledOnce()
  })

  it('keeps a repeat block retryable after a failure', async () => {
    const el = makeLiveBlockEl('/fresh-fail')
    el.setAttribute('data-live-trigger', 'open-fail')
    el.setAttribute('data-live-repeat', '')
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({ ok: false, status: 403, text: () => Promise.resolve('nope') }),
    )
    let forbidden = null
    document.body.addEventListener('live-block-forbidden', (e) => (forbidden = e.detail))

    liveBlock()
    window.dispatchEvent(new Event('open-fail'))
    await vi.waitFor(() => expect(forbidden).not.toBeNull())
    expect(forbidden.status).toBe(403)
    expect(el.hasAttribute('data-live')).toBe(true)
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

// Last on purpose: the first liveBlock() run with window.htmx present installs
// the module-level bridge, whose listeners must not exist during legacy tests.
describe('liveBlock — htmx 4 alias & bridge', () => {
  const fakeHtmx = () => ({ version: '4.0.0-beta6', process: vi.fn() })

  beforeEach(() => {
    document.body.innerHTML = ''
    vi.restoreAllMocks()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('translates a data-live block to htmx attributes instead of fetching', () => {
    const el = makeLiveBlockEl('/block')
    const htmx = fakeHtmx()
    vi.stubGlobal('htmx', htmx)
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    liveBlock()

    expect(el.getAttribute('hx-post')).toBe('/block')
    expect(el.getAttribute('hx-trigger')).toBe('load')
    expect(el.getAttribute('hx-swap')).toBe('outerHTML')
    expect(el.getAttribute('hx-config')).toBe('credentials:"include"')
    expect(el.getAttribute('hx-status:4xx')).toBe('swap:none')
    expect(el.getAttribute('hx-status:5xx')).toBe('swap:none')
    expect(el.hasAttribute('data-live')).toBe(false)
    expect(el.getAttribute('data-live-alias')).toBe('/block')
    expect(htmx.process).toHaveBeenCalledWith(el)
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('leaves a gate-failing block untranslated for the next pass', () => {
    const el = makeLiveBlockEl('/admin-buttons')
    el.setAttribute('data-live-if', 'cookie:pw_auth=1')
    const htmx = fakeHtmx()
    vi.stubGlobal('htmx', htmx)
    vi.stubGlobal('fetch', vi.fn())

    liveBlock()

    expect(htmx.process).not.toHaveBeenCalled()
    expect(el.hasAttribute('data-live')).toBe(true)
    expect(el.hasAttribute('hx-post')).toBe(false)
  })

  it('decodes e:-prefixed rot13 urls when aliasing', () => {
    const el = makeLiveBlockEl('e:/oybpx')
    vi.stubGlobal('htmx', fakeHtmx())
    vi.stubGlobal('fetch', vi.fn())

    liveBlock()

    expect(el.getAttribute('hx-post')).toBe('/block')
    expect(el.getAttribute('data-live-alias')).toBe('/block')
  })

  it('does not re-process an aliased block on a later scan pass', () => {
    const el = makeLiveBlockEl('/block')
    const htmx = fakeHtmx()
    vi.stubGlobal('htmx', htmx)
    vi.stubGlobal('fetch', vi.fn())

    liveBlock()
    liveBlock()

    expect(htmx.process.mock.calls.filter((c) => c[0] === el)).toHaveLength(1)
  })

  it('does not alias under htmx 2', () => {
    makeLiveBlockEl('/block')
    vi.stubGlobal('htmx', { version: '2.0.10', process: vi.fn() })
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({ ok: true, text: () => Promise.resolve('<div>own</div>') }),
    )

    liveBlock()

    expect(window.htmx.process).not.toHaveBeenCalled()
    expect(fetch).toHaveBeenCalledOnce()
  })

  it('translates data-live-trigger to the htmx trigger grammar', () => {
    const once = makeLiveBlockEl('/a')
    once.setAttribute('data-live-trigger', 'open-modal')
    const repeat = makeLiveBlockEl('/b')
    repeat.setAttribute('data-live-trigger', 'open-modal')
    repeat.setAttribute('data-live-repeat', '')
    vi.stubGlobal('htmx', fakeHtmx())
    vi.stubGlobal('fetch', vi.fn())

    liveBlock()

    expect(once.getAttribute('hx-trigger')).toBe('open-modal from:window once')
    expect(once.getAttribute('hx-swap')).toBe('outerHTML')
    expect(repeat.getAttribute('hx-trigger')).toBe('open-modal from:window')
    expect(repeat.getAttribute('hx-swap')).toBe('innerHTML')
  })

  it('bridges htmx:after:swap to DOMChanged and DOMChanged to htmx.process', () => {
    const htmx = fakeHtmx()
    vi.stubGlobal('htmx', htmx)
    liveBlock()

    const domChanged = vi.fn()
    document.addEventListener('DOMChanged', domChanged)
    document.dispatchEvent(new CustomEvent('htmx:after:swap'))
    expect(domChanged).toHaveBeenCalledOnce()
    document.removeEventListener('DOMChanged', domChanged)

    htmx.process.mockClear()
    document.dispatchEvent(new Event('DOMChanged'))
    expect(htmx.process).toHaveBeenCalledWith(document.body)
  })

  it('re-dispatches htmx:response:error on aliased blocks as live-block-forbidden', () => {
    const el = makeLiveBlockEl('/gone')
    vi.stubGlobal('htmx', fakeHtmx())
    liveBlock()

    let forbidden = null
    document.body.addEventListener('live-block-forbidden', (e) => (forbidden = e.detail))
    document.dispatchEvent(
      new CustomEvent('htmx:response:error', {
        detail: { ctx: { sourceElement: el, response: { status: 403 } } },
      }),
    )
    expect(forbidden).toEqual({ status: 403, url: '/gone' })
  })
})

describe('resolveLightboxSources', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    vi.restoreAllMocks()
    // responsiveImage() picks the Liip filter from the viewport: pin it.
    window.innerWidth = 1600
  })

  function stubWebPSupport(supported) {
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({})
    vi.spyOn(HTMLCanvasElement.prototype, 'toDataURL').mockReturnValue(
      supported ? 'data:image/webp;base64,x' : 'data:image/png;base64,x',
    )
  }

  // <span class="glightbox" data-rot="…" data-dwl="…webp"> — what link() renders
  // for a gallery item once obfuscation has done its job.
  function makeCloakedGalleryItem() {
    const span = document.createElement('span')
    span.className = 'glightbox'
    span.setAttribute('data-type', 'image')
    span.setAttribute('data-rot', '/zrqvn/qrsnhyg/2.wct') // /media/default/2.jpg
    span.setAttribute('data-dwl', '/media/default/2.webp')
    span.innerHTML = '<img src="/media/xs/2.jpg" alt="">'
    document.body.appendChild(span)
    return span
  }

  it('decodes data-rot into the data-href the lightbox reads', () => {
    stubWebPSupport(false)
    const span = makeCloakedGalleryItem()

    resolveLightboxSources()

    expect(span.getAttribute('data-href')).toBe('/media/xl/2.jpg')
    expect(span.hasAttribute('data-rot')).toBe(false)
    expect(span.hasAttribute('data-dwl')).toBe(false)

    // It re-runs on every DOMChanged: a second pass must leave the node alone.
    resolveLightboxSources()
    expect(span.getAttribute('data-href')).toBe('/media/xl/2.jpg')
  })

  it('prefers the WebP variant when the browser supports it', () => {
    stubWebPSupport(true)
    const span = makeCloakedGalleryItem()

    resolveLightboxSources()

    expect(span.getAttribute('data-href')).toBe('/media/xl/2.webp')
  })

  it('decodes an absolute video URL, with no WebP variant to prefer', () => {
    stubWebPSupport(true)
    const span = document.createElement('span')
    span.className = 'glightbox'
    span.setAttribute('data-type', 'video')
    // https://www.youtube.com/watch?v=A — the `_` shortcut link() writes for https
    span.setAttribute('data-rot', '_jjj.lbhghor.pbz/jngpu?i=N')
    document.body.appendChild(span)

    resolveLightboxSources()

    expect(span.getAttribute('data-href')).toBe('https://www.youtube.com/watch?v=A')
  })

  it('leaves the node to uncloakLinks() when it is not a lightbox link', () => {
    stubWebPSupport(false)
    const span = document.createElement('span')
    span.setAttribute('data-rot', '_rknzcyr.pbz') // https://example.com
    document.body.appendChild(span)

    resolveLightboxSources()

    expect(span.hasAttribute('data-href')).toBe(false)
    expect(span.getAttribute('data-rot')).toBe('_rknzcyr.pbz')
  })

  it('keeps uncloakLinks() from turning a resolved item into an anchor', async () => {
    stubWebPSupport(false)
    const span = makeCloakedGalleryItem()

    resolveLightboxSources()
    await uncloakLinks()
    span.dispatchEvent(new Event('click'))

    expect(document.querySelector('a')).toBeNull()
    expect(document.body.firstChild).toBe(span)
  })
})
