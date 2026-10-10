import { afterEach, describe, expect, it, vi } from 'vitest'
import type { API } from '@editorjs/editorjs'
import Embed, { EmbedDataToNormalize } from './Embed'
import { MediaToolConfig } from '../Abstract/AbstractMediaTool'

/**
 * The toolbox icon comes from the shared stroke set; the preview keeps its own
 * filled play button, scaled up from the 16px source by attribute replacement.
 */

function embedWith(media: string, data: EmbedDataToNormalize = {}): Embed {
  const api = {
    styles: {
      block: 'ce-block',
      input: 'cdx-input',
      button: 'cdx-button',
      loader: 'loader',
    },
    i18n: { t: (key: string) => key },
  } as unknown as API
  const config = {
    onSelectFile: vi.fn(),
    onUploadFile: vi.fn(),
  } as unknown as MediaToolConfig

  return new Embed({ data: { ...data, media }, config, api, readOnly: false })
}

describe('Embed preview', () => {
  it('draws a 100px filled play button over the thumbnail', () => {
    const tool = embedWith('thumb.jpg')
    ;(tool as any).nodes.preview = document.createElement('div')

    tool.updatePreview()

    const preview: HTMLElement = (tool as any).nodes.preview
    const svg = preview.querySelector('svg')!
    expect(svg.getAttribute('width')).toBe('100')
    expect(svg.getAttribute('height')).toBe('100')
    expect(svg.getAttribute('fill')).toBe('currentColor')
    expect(preview.innerHTML).toContain('/media/md/thumb.jpg')
  })
})

describe('Embed – an upload', () => {
  it('takes the thumbnail and its alternative text from the answer', () => {
    const tool = embedWith('')
    tool.render()

    tool.onUpload({ success: true, file: { media: 'thumb.jpg', name: 'A video' } })

    expect(tool.save()).toEqual({
      serviceUrl: '',
      alternativeText: 'A video',
      media: 'thumb.jpg',
    })
    expect(tool.nodes.fileButton.querySelector('img')?.getAttribute('src')).toBe(
      '/media/md/thumb.jpg',
    )
  })
})

describe('Embed – a thumbnail picked while editing a complete block', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('redraws the preview with it and leaves the block in edit mode', () => {
    const tool = embedWith('thumb.jpg', {
      serviceUrl: 'https://youtu.be/x',
      alternativeText: 'A video',
    })
    document.body.append(tool.render())
    // A complete block opens in preview; its toggle brings the fields back.
    ;(tool.nodes.editInput!.nextElementSibling as HTMLElement).click()

    tool.onUpload({ success: true, file: { media: 'other.jpg', name: 'Another video' } })

    expect(tool.nodes.preview!.innerHTML).toContain('/media/md/other.jpg')
    expect(tool.nodes.preview!.classList.contains('hidden')).toBe(true)
    expect(tool.nodes.editInput!.checked).toBe(false)
  })
})

describe('Embed – thumbnail reference', () => {
  it('reads the thumbnail older blocks kept under image', () => {
    expect(
      Embed.normalizeData({
        serviceUrl: 'https://youtu.be/x',
        image: { media: '1.jpg' },
      }),
    ).toEqual({
      serviceUrl: 'https://youtu.be/x',
      alternativeText: '',
      media: '1.jpg',
    })
  })

  it('previews a thumbnail given as a path where it lives, as the edit view does', () => {
    const tool = embedWith('/media/default/thumb.jpg')
    ;(tool as any).nodes.preview = document.createElement('div')

    tool.updatePreview()

    const preview: HTMLElement = (tool as any).nodes.preview
    expect(preview.innerHTML).toContain("url('/media/default/thumb.jpg')")
    expect(preview.innerHTML).not.toContain('/media/md//media/')
  })
})
