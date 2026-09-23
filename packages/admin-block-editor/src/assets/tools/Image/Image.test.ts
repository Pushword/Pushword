import { describe, it, expect, vi } from 'vitest'
import { API } from '@editorjs/editorjs'
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

function imageWith(media: string): Image {
  const config = {
    onSelectFile: vi.fn(),
    onUploadFile: vi.fn(),
  } as unknown as MediaToolConfig

  return new Image({ data: { media, caption: 'A caption' }, config, api: stubApi() })
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

describe('Image – an upload', () => {
  it('fills the block even when the answer carries no name', () => {
    const tool = imageWith('')
    const wrapper = tool.render()

    tool.onUpload({ success: true, file: { media: 'photo.jpg' } })

    expect(wrapper.querySelector('img')?.getAttribute('src')).toBe('/media/md/photo.jpg')
    expect(tool.save(wrapper)).toEqual({ media: 'photo.jpg', caption: 'A caption' })
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
