import { afterEach, describe, it, expect, vi } from 'vitest'
import { beginMediaPick, MediaUtils } from './media'

/**
 * Image blocks, card lists and quizzes all open the one picker modal through the
 * one hidden <select>, so the pick registry is shared: whoever opens next owns
 * the answer, and the opener it displaced must already have stopped listening.
 */
describe('beginMediaPick', () => {
  it('drops what the previous pick left listening, whichever tool opened it', () => {
    const abandoned = beginMediaPick()
    expect(abandoned.signal.aborted).toBe(false)

    const picking = beginMediaPick()

    expect(abandoned.signal.aborted).toBe(true)
    expect(picking.signal.aborted).toBe(false)
  })
})

describe('MediaUtils.uploadErrorMessage', () => {
  it('returns the server-provided error from the failure body', async () => {
    const response = new Response(JSON.stringify({ success: 0, error: 'mediaTypeMismatch' }), {
      status: 422,
    })
    expect(await MediaUtils.uploadErrorMessage(response)).toBe('mediaTypeMismatch')
  })

  it('falls back to the HTTP status when the body has no error field', async () => {
    const response = new Response(JSON.stringify({ success: 1 }), { status: 500 })
    expect(await MediaUtils.uploadErrorMessage(response)).toBe('HTTP 500')
  })

  it('falls back to the HTTP status when the error field is empty', async () => {
    const response = new Response(JSON.stringify({ success: 0, error: '' }), { status: 422 })
    expect(await MediaUtils.uploadErrorMessage(response)).toBe('HTTP 422')
  })

  it('falls back to the HTTP status when the body is not JSON (e.g. an HTML error page)', async () => {
    const response = new Response('<html><body>Internal Server Error</body></html>', {
      status: 502,
    })
    expect(await MediaUtils.uploadErrorMessage(response)).toBe('HTTP 502')
  })
})

/**
 * Image, Attaches, Embed and Gallery each read the media name out of the shapes
 * their blocks were saved in over time; the fixtures below are those shapes.
 */
describe('MediaUtils.getMediaNameFromData', () => {
  it.each([
    ['a current image block', { media: '1.jpg', caption: 'titre de mon image' }, '1.jpg'],
    [
      'a current embed block',
      { serviceUrl: 'test', alternativeText: 'test', media: '1.jpg' },
      '1.jpg',
    ],
    ['a current attachment file', { media: '1.jpg', size: 2054 }, '1.jpg'],
    ['a gallery item', { media: '2.jpg', caption: '' }, '2.jpg'],
    ['a gallery name left by pw:block:upgrade', '1.jpg', '1.jpg'],
    [
      'an attachment saved with a path',
      { media: '/media/2.jpg', size: 0 },
      '/media/2.jpg',
    ],
  ])('reads %s', (_shape, data, media) => {
    expect(MediaUtils.getMediaNameFromData(data)).toBe(media)
  })

  it.each([
    [
      'an old image block',
      { file: { url: '/media/default/My%20Photo.jpg', name: 'Demo' } },
      'My Photo.jpg',
    ],
    [
      'an old attachment file',
      { url: 'https://example.com/file.pdf', name: 'document.pdf', size: '1024' },
      'file.pdf',
    ],
    ['an old embed block', { serviceUrl: 'test', image: { media: '1.jpg' } }, '1.jpg'],
    [
      'an old gallery item',
      { file: { media: '1.jpg' }, url: '/media/default/1.jpg', caption: 'Demo 1' },
      '1.jpg',
    ],
    ['an old gallery item without url', { file: { media: '2.jpg' } }, '2.jpg'],
  ])('reads %s', (_shape, data, media) => {
    expect(MediaUtils.getMediaNameFromData(data)).toBe(media)
  })

  it('prefers the media field, then the url, then the nested file', () => {
    const data = { media: 'a.jpg', url: '/media/md/b.jpg', file: { media: 'c.jpg' } }

    expect(MediaUtils.getMediaNameFromData(data)).toBe('a.jpg')
    expect(MediaUtils.getMediaNameFromData({ ...data, media: '' })).toBe('b.jpg')
    expect(
      MediaUtils.getMediaNameFromData({ file: data.file, image: { media: 'd.jpg' } }),
    ).toBe('c.jpg')
  })

  it('finds nothing in an empty reference', () => {
    expect(MediaUtils.getMediaNameFromData(undefined)).toBe('')
    expect(MediaUtils.getMediaNameFromData(null)).toBe('')
    expect(MediaUtils.getMediaNameFromData({ caption: 'orphan caption' } as any)).toBe('')
  })
})

/** The server answers /admin/media/resolve/<name> with the media's current name. */
function resolvesTo(fileName: string | null): ReturnType<typeof vi.fn> {
  const fetch = vi.fn(async () =>
    fileName === null
      ? { ok: false, json: async () => ({}) }
      : { ok: true, json: async () => ({ fileName }) },
  )
  vi.stubGlobal('fetch', fetch)

  return fetch
}

describe('MediaUtils.createImage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('previews the media at its preview size, or at the URL it was given', () => {
    expect(MediaUtils.createImage('photo.jpg').getAttribute('src')).toBe(
      '/media/md/photo.jpg',
    )
    expect(
      MediaUtils.createImage(
        'photo.jpg',
        undefined,
        '/media/thumb/photo.jpg',
      ).getAttribute('src'),
    ).toBe('/media/thumb/photo.jpg')
  })

  it('loads the name a renamed media goes by now, and tells the block', async () => {
    const fetch = resolvesTo('new-name.jpg')
    const onRenamed = vi.fn()
    const img = MediaUtils.createImage('Old Name.jpg', onRenamed)

    img.dispatchEvent(new Event('error'))

    await vi.waitFor(() =>
      expect(onRenamed).toHaveBeenCalledWith('new-name.jpg', '/media/md/new-name.jpg'),
    )
    expect(fetch).toHaveBeenCalledWith('/admin/media/resolve/Old%20Name.jpg')
    expect(img.getAttribute('src')).toBe('/media/md/new-name.jpg')
  })

  it('leaves the image alone when the server knows no other name', async () => {
    const fetch = resolvesTo(null)
    const onRenamed = vi.fn()
    const img = MediaUtils.createImage('gone.jpg', onRenamed)

    img.dispatchEvent(new Event('error'))

    await vi.waitFor(() => expect(fetch).toHaveBeenCalled())
    expect(onRenamed).not.toHaveBeenCalled()
    expect(img.getAttribute('src')).toBe('/media/md/gone.jpg')
  })

  it('stops once the current name fails too, rather than reloading it forever', async () => {
    const fetch = resolvesTo('new-name.jpg')
    const onRenamed = vi.fn()
    const img = MediaUtils.createImage('old.jpg', onRenamed)

    img.dispatchEvent(new Event('error'))
    await vi.waitFor(() => expect(onRenamed).toHaveBeenCalledOnce())
    img.dispatchEvent(new Event('error'))
    await vi.waitFor(() => expect(fetch).toHaveBeenCalledTimes(2))

    expect(fetch).toHaveBeenLastCalledWith('/admin/media/resolve/new-name.jpg')
    expect(onRenamed).toHaveBeenCalledOnce()
  })
})
