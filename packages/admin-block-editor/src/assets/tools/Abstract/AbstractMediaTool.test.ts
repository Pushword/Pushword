import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { API } from '@editorjs/editorjs'
import { AbstractMediaTool, MediaToolConfig, UploadResponse } from './AbstractMediaTool'

/**
 * uploadFile() is what both ways into a block end at: the file dialog the Upload
 * button opens, and a file dropped or pasted on the block.
 */

const notify = vi.fn()

function stubApi(): API {
  return {
    styles: { block: 'ce-block', input: 'cdx-input', button: 'cdx-button', loader: 'loader' },
    i18n: { t: (key: string) => key },
    notifier: { show: notify },
  } as unknown as API
}

class MediaTool extends AbstractMediaTool {
  public uploaded: UploadResponse | null = null

  public onUpload(response: UploadResponse): void {
    this.uploaded = response
  }

  public render(): HTMLElement {
    return this.nodes.wrapper
  }

  public save(): Record<string, never> {
    return {}
  }
}

function tool(): MediaTool {
  const config = { onSelectFile: vi.fn(), onUploadFile: vi.fn() } as unknown as MediaToolConfig

  return new MediaTool({ api: stubApi(), config, readOnly: false, data: {} })
}

function respondWith(body: unknown, ok = true, status = 200): void {
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => ({ ok, status, json: async () => body })),
  )
}

beforeEach(() => {
  notify.mockClear()
  // A failing upload logs the reason; the suite asserts on the notice instead.
  vi.spyOn(console, 'error').mockImplementation(() => {})
})

afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

describe('AbstractMediaTool.uploadFile', () => {
  it('posts the file to the media endpoint and fills the block with the answer', async () => {
    const answer = { success: true, file: { media: 'photo.jpg', name: 'A photo' } }
    respondWith(answer)
    const media = tool()

    await media.uploadFile(new File(['x'], 'photo.jpg', { type: 'image/jpeg' }))

    const call = (fetch as unknown as { mock: { calls: any[][] } }).mock.calls[0]!
    expect(call[0]).toBe('/admin/media/block')
    expect(call[1].method).toBe('POST')
    expect((call[1].body as FormData).get('image')).toBeInstanceOf(File)
    expect(media.uploaded).toEqual(answer)
  })

  it('shows the block as uploading while the request is in flight', async () => {
    respondWith({ success: true, file: { media: 'photo.jpg' } })
    const media = tool()

    const pending = media.uploadFile(new File(['x'], 'photo.jpg'))
    expect(media.nodes.wrapper.classList.contains('image-tool--loading')).toBe(true)

    await pending
  })

  it('surfaces the reason the server gave rather than a bare retry notice', async () => {
    respondWith({ success: 0, error: 'Unsupported mime type.' }, false, 422)
    const media = tool()

    await media.uploadFile(new File(['x'], 'virus.exe'))

    expect(media.uploaded).toBeNull()
    expect(notify).toHaveBeenCalledWith(
      expect.objectContaining({
        message: expect.stringContaining('Unsupported mime type.'),
        style: 'error',
      }),
    )
  })

  it('leaves the block out of its loading state when the upload fails', async () => {
    respondWith({}, false, 500)
    const media = tool()

    await media.uploadFile(new File(['x'], 'photo.jpg'))

    expect(media.nodes.wrapper.classList.contains('image-tool--loading')).toBe(false)
  })
})
