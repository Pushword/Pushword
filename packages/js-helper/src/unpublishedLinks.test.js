import { describe, it, expect, vi, beforeEach } from 'vitest'
import { restoreUnpublishedLinks } from './unpublishedLinks.js'

function makeSpan(href = '/draft') {
  const span = document.createElement('span')
  span.dataset.status = 'unpublished'
  span.dataset.href = href
  span.title = 'Page en cours de publication'
  span.innerHTML = 'label'
  document.body.appendChild(span)
  return span
}

const clearCookie = () => {
  document.cookie = 'pw_auth=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
}

describe('restoreUnpublishedLinks', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    clearCookie()
    vi.restoreAllMocks()
  })

  it('leaves the span alone without the pw_auth cookie', () => {
    makeSpan()

    restoreUnpublishedLinks()

    expect(document.querySelector('a')).toBeNull()
    expect(document.querySelector('span[data-status="unpublished"]')).not.toBeNull()
  })

  it('restores the <a> for an editor carrying pw_auth=1', () => {
    makeSpan('/draft')
    document.cookie = 'pw_auth=1'

    restoreUnpublishedLinks()

    const a = document.querySelector('a')
    expect(a).not.toBeNull()
    expect(a.getAttribute('href')).toBe('/draft')
    expect(a.dataset.unpublished).toBe('1')
    expect(a.innerHTML).toBe('label')
    expect(a.title).toBe('Page en cours de publication')
    expect(document.querySelector('span[data-status="unpublished"]')).toBeNull()
  })

  // The whole point of reading a cookie: a 401 probe is logged by the browser's
  // network stack as a console error and counted by Lighthouse, and no JS can
  // silence it. Any reintroduced request would bring the error back.
  it('never issues a network request', () => {
    makeSpan()
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)

    restoreUnpublishedLinks()
    document.cookie = 'pw_auth=1'
    restoreUnpublishedLinks()

    expect(fetchMock).not.toHaveBeenCalled()
    expect(document.querySelector('a')).not.toBeNull()
  })

  it('does not mistake a cookie whose name merely ends in pw_auth', () => {
    makeSpan()
    document.cookie = 'not_pw_auth=1'

    restoreUnpublishedLinks()

    expect(document.querySelector('a')).toBeNull()
    document.cookie = 'not_pw_auth=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
  })

  // A real editor's jar is never a single cookie: the session, the consent choice
  // and pw_auth sit side by side, so the entry has to be found among the others.
  it('finds pw_auth among other cookies', () => {
    makeSpan()
    document.cookie = 'not_pw_auth=1'
    document.cookie = 'pw_auth=1'
    document.cookie = 'consent=all'

    restoreUnpublishedLinks()

    expect(document.querySelector('a')).not.toBeNull()
    document.cookie = 'not_pw_auth=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
    document.cookie = 'consent=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
  })

  it('is a no-op when the page carries no unpublished link', () => {
    document.cookie = 'pw_auth=1'

    expect(() => restoreUnpublishedLinks()).not.toThrow()
    expect(document.querySelector('a')).toBeNull()
  })
})
