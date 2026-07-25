import { describe, it, expect, vi, beforeEach } from 'vitest'
import { loadVariant, initVariantLinks } from './variantLinks.js'

function variantHtml(text) {
  return `<!doctype html><html><body><main id="content">${text}</main></body></html>`
}

function stubFetch(html) {
  const fetchMock = vi.fn(() => Promise.resolve({ text: () => Promise.resolve(html) }))
  vi.stubGlobal('fetch', fetchMock)

  return fetchMock
}

// jsdom cannot navigate: stand in for window.location so reload stays assertable.
const reload = vi.fn()
const nativeLocation = window.location
Object.defineProperty(window, 'location', {
  configurable: true,
  value: {
    get href() {
      return nativeLocation.href
    },
    get pathname() {
      return nativeLocation.pathname
    },
    reload,
  },
})

beforeEach(() => {
  document.body.innerHTML = ''
  vi.restoreAllMocks()
  reload.mockClear()
  history.replaceState({}, '', '/master')
})

describe('loadVariant', () => {
  it('fetches the variant, swaps the content zone and pushes the variant URL', async () => {
    document.body.innerHTML = '<main id="content">master</main>'
    const fetchMock = stubFetch(variantHtml('variant body'))

    await loadVariant('/the-variant', '#content')

    expect(fetchMock).toHaveBeenCalledWith('/the-variant', { credentials: 'same-origin' })
    expect(document.querySelector('#content').textContent).toBe('variant body')
    expect(window.location.pathname).toBe('/the-variant')
  })

  it('is a no-op when the content zone is missing', async () => {
    const fetchMock = stubFetch(variantHtml('variant body'))

    await loadVariant('/the-variant', '#content')

    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('is a no-op when the variant URL is empty', async () => {
    document.body.innerHTML = '<main id="content">master</main>'
    const fetchMock = stubFetch(variantHtml('variant body'))

    await loadVariant('', '#content')

    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('keeps the master content when the fetch fails', async () => {
    document.body.innerHTML = '<main id="content">master</main>'
    vi.stubGlobal(
      'fetch',
      vi.fn(() => Promise.reject(new Error('offline'))),
    )

    await loadVariant('/the-variant', '#content')

    expect(document.querySelector('#content').textContent).toBe('master')
    expect(window.location.pathname).toBe('/master')
  })

  it('keeps the master content when the response has no content zone', async () => {
    document.body.innerHTML = '<main id="content">master</main>'
    stubFetch('<!doctype html><body>404')

    await loadVariant('/the-variant', '#content')

    expect(document.querySelector('#content').textContent).toBe('master')
    expect(window.location.pathname).toBe('/master')
  })

  it('dispatches DOMChanged so components can re-initialise', async () => {
    document.body.innerHTML = '<main id="content">master</main>'
    stubFetch(variantHtml('v'))
    const onChanged = vi.fn()
    document.addEventListener('DOMChanged', onChanged)

    await loadVariant('/the-variant', '#content')

    expect(onChanged).toHaveBeenCalledOnce()
  })
})

describe('initVariantLinks', () => {
  it('intercepts a click on a [data-variant] link', async () => {
    document.body.innerHTML =
      '<main id="content">master</main><a href="/master" data-variant="/the-variant">go</a>'
    const fetchMock = stubFetch(variantHtml('swapped'))

    initVariantLinks({ zone: '#content' })
    document.querySelector('a[data-variant]').click()
    await Promise.resolve()
    await Promise.resolve()

    expect(fetchMock).toHaveBeenCalledWith('/the-variant', { credentials: 'same-origin' })
  })

  it('lets a modified click through so the browser opens the master in a new tab', () => {
    document.body.innerHTML =
      '<main id="content">master</main><a href="/master" data-variant="/the-variant">go</a>'
    const fetchMock = stubFetch(variantHtml('swapped'))

    initVariantLinks({ zone: '#content' })
    const event = new MouseEvent('click', {
      bubbles: true,
      cancelable: true,
      ctrlKey: true,
    })
    document.querySelector('a[data-variant]').dispatchEvent(event)

    expect(fetchMock).not.toHaveBeenCalled()
    expect(event.defaultPrevented).toBe(false)
  })

  it('ignores plain links without data-variant', () => {
    document.body.innerHTML = '<main id="content">master</main><a href="#stay">plain</a>'
    const fetchMock = stubFetch(variantHtml('swapped'))

    initVariantLinks({ zone: '#content' })
    document.querySelector('a[href="#stay"]').click()

    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('keeps the swapped content on an in-page hash navigation', async () => {
    document.body.innerHTML = '<main id="content">master</main>'
    stubFetch(variantHtml('variant body'))

    initVariantLinks({ zone: '#content' })
    await loadVariant('/the-variant', '#content')
    history.replaceState({}, '', '/the-variant#anchor')
    window.dispatchEvent(new PopStateEvent('popstate'))

    expect(window.location.reload).not.toHaveBeenCalled()
  })

  it('reloads when navigating back off a variant', async () => {
    document.body.innerHTML = '<main id="content">master</main>'
    stubFetch(variantHtml('variant body'))

    initVariantLinks({ zone: '#content' })
    await loadVariant('/the-variant', '#content')
    history.replaceState({}, '', '/master')
    window.dispatchEvent(new PopStateEvent('popstate'))

    expect(window.location.reload).toHaveBeenCalled()
  })
})
