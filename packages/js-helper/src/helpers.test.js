import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { liveBlock } from './helpers.js'

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
    vi.restoreAllMocks()
  })

  it('replaces outerHTML and dispatches DOMChanged on 200', async () => {
    const el = makeLiveBlockEl('/block')
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
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 403,
        text: () => Promise.resolve('<html>login page</html>'),
      }),
    )

    let forbiddenDetail = null
    document.body.addEventListener('live-block-forbidden', (e) => {
      forbiddenDetail = e.detail
    })

    liveBlock()

    await vi.waitFor(() => expect(forbiddenDetail).not.toBeNull())
    expect(forbiddenDetail.status).toBe(403)
    // original block must still be present
    expect(document.body.querySelector('[data-live]')).not.toBeNull()
    expect(document.body.innerHTML).not.toContain('login page')
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
