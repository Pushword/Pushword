import { describe, expect, it, vi } from 'vitest'
import type { API } from '@editorjs/editorjs'
import Embed from './Embed'
import { MediaToolConfig } from '../Abstract/AbstractMediaTool'

/**
 * The toolbox icon comes from the shared stroke set; the preview keeps its own
 * filled play button, scaled up from the 16px source by attribute replacement.
 */

function embedWith(media: string): Embed {
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

  return new Embed({ data: { media }, config, api, readOnly: false })
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
