import { describe, it, expect, vi, beforeEach } from 'vitest'
import { loadVariant, initVariantLinks } from './variantLinks.js'

function variantHtml(text) {
  return `<!doctype html><html><body><main id="content">${text}</main></body></html>`
}

beforeEach(() => {
  document.body.innerHTML = ''
  vi.restoreAllMocks()
  history.replaceState({}, '', '/master')
})

describe('loadVariant', () => {
  it('fetches the variant, swaps the content zone and pushes the variant URL', async () => {
    document.body.innerHTML = '<main id="content">master</main>'
    vi.stubGlobal(
      'fetch',
      vi.fn(() => Promise.resolve({ text: () => Promise.resolve(variantHtml('variant body')) })),
    )

    await loadVariant('/the-variant', '#content')

    expect(fetch).toHaveBeenCalledWith('/the-variant', { credentials: 'same-origin' })
    expect(document.querySelector('#content').textContent).toBe('variant body')
    expect(window.location.pathname).toBe('/the-variant')
  })

  it('is a no-op when the content zone is missing', async () => {
    vi.stubGlobal('fetch', vi.fn())
    await loadVariant('/the-variant', '#content')
    expect(fetch).not.toHaveBeenCalled()
  })

  it('dispatches DOMChanged so components can re-initialise', async () => {
    document.body.innerHTML = '<main id="content">master</main>'
    vi.stubGlobal(
      'fetch',
      vi.fn(() => Promise.resolve({ text: () => Promise.resolve(variantHtml('v')) })),
    )
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
    const fetchMock = vi.fn(() =>
      Promise.resolve({ text: () => Promise.resolve(variantHtml('swapped')) }),
    )
    vi.stubGlobal('fetch', fetchMock)

    initVariantLinks({ zone: '#content' })
    document.querySelector('a[data-variant]').click()
    await Promise.resolve()
    await Promise.resolve()

    expect(fetchMock).toHaveBeenCalledWith('/the-variant', { credentials: 'same-origin' })
  })

  it('ignores plain links without data-variant', () => {
    document.body.innerHTML = '<main id="content">master</main><a href="#stay">plain</a>'
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    initVariantLinks({ zone: '#content' })
    document.querySelector('a[href="#stay"]').click()

    expect(fetchMock).not.toHaveBeenCalled()
  })
})
