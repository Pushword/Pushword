import { afterEach, describe, it, expect, vi } from 'vitest'
import { API, OutputBlockData } from '@editorjs/editorjs'
import Image from './Image'
import { MediaToolConfig } from '../Abstract/AbstractMediaTool'

/**
 * A filled block hides its Select/Upload buttons (`.image-tool--filled` in the
 * stylesheet), so until the delete button existed the only way to change the
 * picture was to delete the block and build a new one.
 */

function stubApi(): API {
  return {
    styles: { block: 'ce-block', input: 'cdx-input', button: 'cdx-button', loader: 'loader' },
    i18n: { t: (key: string) => key },
  } as unknown as API
}

function imageWith(media: string, api: API = stubApi(), readOnly = false): Image {
  const config = {
    onSelectFile: vi.fn(),
    onUploadFile: vi.fn(),
  } as unknown as MediaToolConfig

  return new Image({ data: { media, caption: 'A caption' }, config, api, readOnly })
}

function deleteButtonOf(wrapper: HTMLElement): HTMLElement {
  const button = wrapper.querySelector<HTMLElement>('.media-tool__delete')
  if (!button) throw new Error('render() did not build the delete button')

  return button
}

describe('Image – removing the media', () => {
  it('empties the block and puts it back in the state that shows the buttons', () => {
    const tool = imageWith('photo.jpg')
    const wrapper = tool.render()
    // The block only counts as filled once the picture has loaded.
    wrapper.querySelector('img')!.dispatchEvent(new Event('load'))
    expect(wrapper.classList.contains('image-tool--filled')).toBe(true)

    deleteButtonOf(wrapper).click()

    expect(tool.save(wrapper).media).toBe('')
    expect(wrapper.classList.contains('image-tool--empty')).toBe(true)
    expect(wrapper.querySelector('img')).toBeNull()
  })

  it('keeps the caption, which describes what the block is for', () => {
    const tool = imageWith('photo.jpg')
    const wrapper = tool.render()

    deleteButtonOf(wrapper).click()
    tool.onUpload({ success: true, file: { media: 'other.jpg' } })

    expect(tool.save(wrapper)).toEqual({ media: 'other.jpg', caption: 'A caption' })
  })

  it('does not submit the admin form it is rendered inside', () => {
    const tool = imageWith('photo.jpg')

    // An implicit type="submit" would save the page on every click.
    expect(deleteButtonOf(tool.render()).getAttribute('type')).toBe('button')
  })
})

describe('Image – the inline uploader', () => {
  it('offers pictures only', () => {
    expect(imageWith('').uploadAccept).toBe('image/*')
  })
})

describe('Image – converting to a gallery', () => {
  function conversionApi(tunes?: OutputBlockData['tunes']): API {
    return {
      ...stubApi(),
      blocks: {
        getBlockByElement: vi.fn(() => ({
          id: 'image-id',
          save: async () => (tunes ? { tunes } : {}),
        })),
        getBlockIndex: vi.fn(() => 2),
        insert: vi.fn(),
        update: vi.fn(async () => ({ id: 'image-id' })),
      },
      caret: { setToBlock: vi.fn() },
    } as unknown as API
  }

  it('replaces this image in place with a gallery containing its media and edited caption', async () => {
    const api = conversionApi()
    const tool = imageWith('photo.jpg', api)
    const wrapper = tool.render()
    wrapper.querySelector('.image-tool__caption')!.textContent =
      'Edited alternative text'
    const action = tool.renderSettings()[0]!

    expect(action.title).toBe('Convert to gallery')
    expect(action.closeOnActivate).toBe(true)
    await action.onActivate()

    expect(api.blocks.getBlockByElement).toHaveBeenCalledWith(wrapper)
    expect(api.blocks.getBlockIndex).toHaveBeenCalledWith('image-id')
    expect(api.blocks.insert).toHaveBeenCalledExactlyOnceWith(
      'gallery',
      { items: [{ media: 'photo.jpg', caption: 'Edited alternative text' }] },
      {},
      2,
      true,
      true,
      'image-id',
    )
    expect(api.blocks.update).not.toHaveBeenCalled()
  })

  it('keeps common and inactive tunes when converting an image to a gallery', async () => {
    const tunes = {
      anchor: 'image-anchor',
      class: 'wide-image',
      textAlign: 'center',
      linkTune: { url: 'https://example.com', targetBlank: true },
    }
    const api = conversionApi(tunes)
    const tool = imageWith('photo.jpg', api)
    tool.render()

    await tool.renderSettings()[0]!.onActivate()

    expect(api.blocks.update).toHaveBeenCalledExactlyOnceWith('image-id', undefined, tunes)
    expect(api.caret.setToBlock).toHaveBeenCalledExactlyOnceWith('image-id')
  })

  it('does not convert an image whose media was removed', () => {
    const api = conversionApi()
    const tool = imageWith('photo.jpg', api)
    const wrapper = tool.render()
    const action = tool.renderSettings()[0]!
    deleteButtonOf(wrapper).click()

    expect(tool.renderSettings()).toEqual([])
    action.onActivate()

    expect(api.blocks.insert).not.toHaveBeenCalled()
  })

  it('does not offer conversion in read-only mode', () => {
    expect(imageWith('photo.jpg', stubApi(), true).renderSettings()).toEqual([])
  })
})

describe('Image – an upload', () => {
  it('fills the block even when the answer carries no name', () => {
    const tool = imageWith('')
    const wrapper = tool.render()

    tool.onUpload({ success: true, file: { media: 'photo.jpg' } })

    expect(wrapper.querySelector('img')?.getAttribute('src')).toBe('/media/md/photo.jpg')
    expect(tool.save(wrapper)).toEqual({ media: 'photo.jpg', caption: 'A caption' })
  })

  it('takes its caption from the name the answer gives', () => {
    const tool = imageWith('')
    const wrapper = tool.render()

    tool.onUpload({ success: true, file: { media: 'photo.jpg', name: 'A photo' } })

    expect(tool.save(wrapper)).toEqual({ media: 'photo.jpg', caption: 'A photo' })
  })
})

describe('Image – an older block', () => {
  it('takes the media name from the file url and the caption from its name', () => {
    expect(
      Image.normalizeData({ file: { url: '/media/default/1.jpg', name: 'Demo 1' } }),
    ).toEqual({
      media: '1.jpg',
      caption: 'Demo 1',
    })
  })
})

describe('Image – a renamed media', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows and saves the name the media goes by now', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({ ok: true, json: async () => ({ fileName: 'new-name.jpg' }) })),
    )
    const tool = imageWith('Old Name.jpg')
    const wrapper = tool.render()
    const img = wrapper.querySelector('img')!

    img.dispatchEvent(new Event('error'))

    await vi.waitFor(() => expect(img.getAttribute('src')).toBe('/media/md/new-name.jpg'))
    expect(tool.save(wrapper).media).toBe('new-name.jpg')
  })
})

describe('Image – markdown', () => {
  it('writes the media name, not the editor preview path', () => {
    expect(Image.exportToMarkdown({ media: 'photo.jpg', caption: 'Alt' })).toBe('![Alt](photo.jpg)')
  })

  it('claims a chunk that is the image alone', () => {
    expect(Image.isItMarkdownExported('![Alt](photo.jpg)')).toBe(true)
    expect(Image.isItMarkdownExported('[![Alt](photo.jpg)](/page){target="_blank"}')).toBe(true)
  })

  it('claims an image without alt text', () => {
    expect(Image.isItMarkdownExported('![](photo.jpg)')).toBe(true)
  })

  it.each([
    [{ url: '/page', targetBlank: false, hideForBot: false }],
    [{ url: '/page', targetBlank: true, hideForBot: false }],
    [{ url: '/page', targetBlank: false, hideForBot: true }],
    [{ url: '/page', targetBlank: true, hideForBot: true }],
  ])('claims back the linked image it writes for %o', (linkTune) => {
    const markdown = Image.exportToMarkdown({ media: 'photo.jpg', caption: 'Alt' }, { linkTune } as any)

    expect(Image.isItMarkdownExported(markdown)).toBe(true)
  })

  it('leaves an image with text around it to the paragraph', () => {
    expect(Image.isItMarkdownExported('![Alt](photo.jpg) du texte après')).toBe(false)
    expect(Image.isItMarkdownExported('Avant ![Alt](photo.jpg)')).toBe(false)
  })
})
