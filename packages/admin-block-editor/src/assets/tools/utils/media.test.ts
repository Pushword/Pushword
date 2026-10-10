import { afterEach, beforeEach, describe, it, expect, vi } from 'vitest'
import { MediaUtils, openMediaPicker, pickedMediaName } from './media'

const FIELD_ID = 'editorjs_1_inline_image'
const MODAL_URL = '/admin/media?pwMediaPicker=1'

function pickerOnPage(): { choose: HTMLButtonElement; upload: HTMLButtonElement } {
  document.body.innerHTML = `
    <div class="pw-media-picker">
      <select id="${FIELD_ID}" data-pw-media-picker-modal-url="${MODAL_URL}"></select>
      <button data-pw-media-picker-action="choose"></button>
      <button data-pw-media-picker-action="upload"></button>
    </div>
  `
  const button = (action: string) =>
    document.querySelector<HTMLButtonElement>(
      `[data-pw-media-picker-action="${action}"]`,
    )!

  return { choose: button('choose'), upload: button('upload') }
}

function pickerPosts(data: unknown, origin = window.location.origin): void {
  window.dispatchEvent(new MessageEvent('message', { origin, data }))
}

function pickerSends(fileName: string, fieldId = FIELD_ID): void {
  pickerPosts({ type: 'pw-media-picker-select', fieldId, media: { id: 7, fileName } })
}

/**
 * Image blocks, card lists and quizzes all open the one picker modal through the
 * one hidden <select>, so the message a pick answers with names that select, not
 * the block: whoever opened last owns the answer.
 */
describe('openMediaPicker', () => {
  beforeEach(() => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
  })

  afterEach(() => {
    vi.restoreAllMocks()
    document.body.innerHTML = ''
  })

  it('opens the modal through the choose button, or the upload one when asked', () => {
    const { choose, upload } = pickerOnPage()
    const chosen = vi.spyOn(choose, 'click')
    const uploaded = vi.spyOn(upload, 'click')

    openMediaPicker({ onPick: vi.fn() })
    expect(chosen).toHaveBeenCalledOnce()

    openMediaPicker({ action: 'upload', onPick: vi.fn() })
    expect(uploaded).toHaveBeenCalledOnce()
  })

  it('opens nothing and says so to its caller when the page has no picker', () => {
    expect(openMediaPicker({ onPick: vi.fn() })).toBeNull()

    pickerOnPage()
    expect(
      openMediaPicker({ field: '[id*="inline_attaches"]', onPick: vi.fn() }),
    ).toBeNull()
  })

  it('hands over the media the picker posts for its field', () => {
    pickerOnPage()
    const onPick = vi.fn()

    openMediaPicker({ onPick })
    pickerSends('photo.jpg')

    expect(onPick).toHaveBeenCalledWith({ id: 7, fileName: 'photo.jpg' })
  })

  it('ignores another origin, another message type, another field and an empty pick', () => {
    pickerOnPage()
    const onPick = vi.fn()
    openMediaPicker({ onPick })

    pickerPosts(
      { type: 'pw-media-picker-select', fieldId: FIELD_ID, media: { id: 1 } },
      'https://evil.test',
    )
    pickerPosts({
      type: 'pw-media-picker-multi-select',
      fieldId: FIELD_ID,
      items: [{ id: 1 }],
    })
    pickerSends('other.jpg', 'editorjs_2_inline_image')
    pickerPosts({ type: 'pw-media-picker-select', fieldId: FIELD_ID })
    pickerPosts(null)
    expect(onPick).not.toHaveBeenCalled()

    // None of those was the pick's answer, so the real one still lands
    pickerSends('photo.jpg')
    expect(onPick).toHaveBeenCalledOnce()
  })

  it('stops listening once its pick lands', () => {
    pickerOnPage()
    const onPick = vi.fn()

    openMediaPicker({ onPick })
    pickerSends('photo.jpg')
    pickerSends('other.jpg')

    expect(onPick).toHaveBeenCalledOnce()
  })

  it('stops listening when its caller aborts, e.g. a block destroyed mid-pick', () => {
    pickerOnPage()
    const onPick = vi.fn()

    openMediaPicker({ onPick })!.abort()
    pickerSends('photo.jpg')

    expect(onPick).not.toHaveBeenCalled()
  })

  it('drops what the previous pick left listening, whichever tool opened it', () => {
    pickerOnPage()
    const abandoned = vi.fn()
    const picking = vi.fn()

    // The editor opens the picker, closes it without choosing, then picks for another block
    const first = openMediaPicker({ onPick: abandoned })!
    openMediaPicker({ onPick: picking })
    pickerSends('photo.jpg')

    expect(first.signal.aborted).toBe(true)
    expect(abandoned).not.toHaveBeenCalled()
    expect(picking).toHaveBeenCalledOnce()
  })

  it('in multi mode, hands over the whole selection and nothing else', () => {
    pickerOnPage()
    const onPick = vi.fn()
    openMediaPicker({ multi: true, onPick })

    pickerSends('single.jpg')
    expect(onPick).not.toHaveBeenCalled()

    pickerPosts({
      type: 'pw-media-picker-multi-select',
      fieldId: FIELD_ID,
      items: [
        { id: 1, fileName: 'one.jpg' },
        { id: 2, fileName: 'two.jpg' },
      ],
    })
    expect(onPick).toHaveBeenCalledWith([
      { id: 1, fileName: 'one.jpg' },
      { id: 2, fileName: 'two.jpg' },
    ])
  })

  it('in multi mode, opens the modal on a multi-select URL and restores the one it borrowed', () => {
    const { choose } = pickerOnPage()
    const select = document.querySelector('select')!
    let urlAtClick = ''
    choose.addEventListener('click', () => {
      urlAtClick = select.dataset.pwMediaPickerModalUrl || ''
    })

    openMediaPicker({ multi: true, onPick: vi.fn() })

    expect(new URL(urlAtClick).searchParams.get('pwMediaPickerMulti')).toBe('1')
    expect(select.dataset.pwMediaPickerModalUrl).toBe(MODAL_URL)
  })

  it('uses and restores the legacy popup URL when opening in multi mode', () => {
    const { choose } = pickerOnPage()
    const select = document.querySelector('select')!
    const legacyUrl = '/admin/media?existing=1'
    delete select.dataset.pwMediaPickerModalUrl
    select.dataset.pwAdminPopupModalUrl = legacyUrl
    let urlAtClick = ''
    choose.addEventListener('click', () => {
      urlAtClick = select.dataset.pwAdminPopupModalUrl || ''
    })

    openMediaPicker({ multi: true, onPick: vi.fn() })

    const openedUrl = new URL(urlAtClick)
    expect(openedUrl.searchParams.get('existing')).toBe('1')
    expect(openedUrl.searchParams.get('pwMediaPickerMulti')).toBe('1')
    expect(select.dataset.pwAdminPopupModalUrl).toBe(legacyUrl)
    expect(select.dataset.pwMediaPickerModalUrl).toBeUndefined()
  })
})

describe('pickedMediaName', () => {
  it('is the file name, else the id', () => {
    expect(pickedMediaName({ id: 7, fileName: 'photo.jpg' })).toBe('photo.jpg')
    expect(pickedMediaName({ id: 7, fileName: '' })).toBe('7')
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
    ['a fileName reference', { fileName: '1.jpg' }, '1.jpg'],
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

  it('prefers media, then fileName, then the url, then the nested file', () => {
    const data = {
      media: 'a.jpg',
      fileName: 'b.jpg',
      url: '/media/md/c.jpg',
      file: { media: 'd.jpg' },
    }

    expect(MediaUtils.getMediaNameFromData(data)).toBe('a.jpg')
    expect(MediaUtils.getMediaNameFromData({ ...data, media: '' })).toBe('b.jpg')
    expect(
      MediaUtils.getMediaNameFromData({ ...data, media: '', fileName: '' }),
    ).toBe('c.jpg')
    expect(
      MediaUtils.getMediaNameFromData({ file: data.file, image: { media: 'e.jpg' } }),
    ).toBe('d.jpg')
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
