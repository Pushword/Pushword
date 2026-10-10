import { afterEach, describe, expect, it, vi } from 'vitest'
import type { API } from '@editorjs/editorjs'
import Gallery from './Gallery'
import { MediaToolConfig } from '../Abstract/AbstractMediaTool'

const notify = vi.fn()

function galleryWith(data: any): Gallery {
  const api = {
    styles: {
      block: 'ce-block',
      input: 'cdx-input',
      button: 'cdx-button',
      loader: 'loader',
    },
    i18n: { t: (key: string) => key },
    notifier: { show: notify },
  } as unknown as API
  const config = {
    onSelectFile: vi.fn(),
    onUploadFile: vi.fn(),
  } as unknown as MediaToolConfig

  return new Gallery({ data, config, api, readOnly: false })
}

describe('Gallery.normalizeData', () => {
  it('keeps the current shape as it is', () => {
    const data = {
      items: [
        { media: '1.jpg', caption: '' },
        { media: '2.jpg', caption: 'Two' },
      ],
    }

    expect(Gallery.normalizeData(data)).toEqual(data)
  })

  it('reads the bare array of names pw:block:upgrade left', () => {
    expect(Gallery.normalizeData(['1.jpg', '2.jpg'] as any)).toEqual({
      items: [
        { media: '1.jpg', caption: '' },
        { media: '2.jpg', caption: '' },
      ],
    })
  })

  it('reads the items older galleries saved, with their caption', () => {
    const legacy = [
      { file: { media: '1.jpg' }, url: '/media/default/1.jpg', caption: 'Demo 1' },
      { file: { media: '2.jpg' } },
    ]

    expect(Gallery.normalizeData(legacy as any)).toEqual({
      items: [
        { media: '1.jpg', caption: 'Demo 1' },
        { media: '2.jpg', caption: '' },
      ],
    })
    expect(Gallery.normalizeData({ items: legacy } as any)).toEqual(
      Gallery.normalizeData(legacy as any),
    )
  })

  it('reads a name listed in the items as it reads one in the bare array', () => {
    expect(Gallery.normalizeData({ items: ['1.jpg'] } as any)).toEqual({
      items: [{ media: '1.jpg', caption: '' }],
    })
  })

  it('drops what holds no media', () => {
    expect(
      Gallery.normalizeData({ items: [null, { caption: 'orphan' }, ''] } as any),
    ).toEqual({
      items: [],
    })
    expect(Gallery.normalizeData({} as any)).toEqual({ items: [] })
  })
})

/** An upload or a pick lands in the empty item onFileLoading() opened for it. */
describe('Gallery – adding a media', () => {
  afterEach(() => {
    notify.mockClear()
  })

  it('fills the item opened for it and saves it after the others', () => {
    const tool = galleryWith({ items: [{ media: '1.jpg', caption: 'One' }] })
    tool.render()

    tool.onFileLoading()
    tool.onUpload({
      success: true,
      file: { media: '2.jpg', url: '/media/md/2.jpg', name: 'Two' },
    })

    expect(tool.save().items).toEqual([
      { media: '1.jpg', caption: 'One' },
      { media: '2.jpg', caption: 'Two' },
    ])
  })

  it('refuses a media already in the gallery and drops the item opened for it', () => {
    const tool = galleryWith({ items: [{ media: '1.jpg', caption: 'One' }] })
    const wrapper = tool.render()

    tool.onFileLoading()
    tool.onUpload({ success: true, file: { media: '1.jpg', url: '/media/md/1.jpg' } })

    expect(notify).toHaveBeenCalledWith(expect.objectContaining({ style: 'error' }))
    expect(wrapper.querySelectorAll('.cdxcarousel-block')).toHaveLength(1)
    expect(tool.save().items).toEqual([{ media: '1.jpg', caption: 'One' }])
  })
})

describe('Gallery – a renamed media', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows and saves the name the media goes by now', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({ ok: true, json: async () => ({ fileName: 'new-name.jpg' }) })),
    )
    const tool = galleryWith({ items: [{ media: 'Old Name.jpg', caption: 'Alt' }] })
    const wrapper = tool.render()
    const img = wrapper.querySelector('img')!

    img.dispatchEvent(new Event('error'))

    const item = wrapper.querySelector<HTMLElement>('.cdxcarousel-item')!
    await vi.waitFor(() =>
      expect(item.style.getPropertyValue('--bg-image-url')).toBe(
        "url('/media/md/new-name.jpg')",
      ),
    )
    expect(tool.save().items).toEqual([{ media: 'new-name.jpg', caption: 'Alt' }])
  })
})
