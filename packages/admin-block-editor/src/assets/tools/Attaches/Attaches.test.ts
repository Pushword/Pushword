import { describe, it, expect, vi } from 'vitest'
import { API, BlockAPI } from '@editorjs/editorjs'
import Attaches from './Attaches'
import { MediaToolConfig } from '../Abstract/AbstractMediaTool'

function stubApi(): API {
  return {
    styles: { block: 'ce-block', input: 'cdx-input', button: 'cdx-button', loader: 'loader' },
    i18n: { t: (key: string) => key },
  } as unknown as API
}

function attachesWith(media: string): {
  tool: Attaches
  dispatchChange: () => void
  config: MediaToolConfig
} {
  const dispatchChange = vi.fn()
  const config = {
    onSelectFile: vi.fn(),
    onUploadFile: vi.fn(),
  } as unknown as MediaToolConfig

  const tool = new Attaches({
    data: { title: 'Report', file: { media, size: 2048 } },
    config,
    api: stubApi(),
    readOnly: false,
    block: { dispatchChange } as unknown as BlockAPI,
  })

  return { tool, dispatchChange, config }
}

describe('Attaches – removing the file', () => {
  it('empties the block and brings the Select/Upload buttons back', () => {
    const { tool, dispatchChange } = attachesWith('report.pdf')
    const holder = tool.render()

    holder.querySelector<HTMLElement>('.media-tool__delete')!.click()

    expect(tool.save(holder)).toEqual({ title: '', file: { media: '', size: 0 } })
    expect(holder.querySelector('.cdx-attaches__file-info')).toBeNull()
    expect(holder.querySelector('.cdx-input-labeled-preview')).not.toBeNull()
    // Nothing in the DOM changed for editor.js to observe otherwise.
    expect(dispatchChange).toHaveBeenCalled()
  })

  it('renders one file at a time when another replaces it', () => {
    const { tool } = attachesWith('report.pdf')
    const holder = tool.render()

    tool.onUpload({ success: true, file: { media: 'other.pdf', name: 'Other', size: 10 } })

    expect(holder.querySelectorAll('.cdx-attaches__file-info')).toHaveLength(1)
    expect(holder.querySelectorAll('.media-tool__delete')).toHaveLength(1)
  })
})

describe('Attaches – an upload', () => {
  it('saves the file the answer names, titled by its name, else its title', () => {
    const { tool } = attachesWith('report.pdf')
    const holder = tool.render()

    tool.onUpload({
      success: true,
      file: { media: 'other.pdf', name: 'Other', size: 10 },
    })
    expect(tool.save(holder)).toEqual({
      title: 'Other',
      file: { media: 'other.pdf', size: 10 },
    })

    // No size in the answer: the replaced file's size does not stay behind
    tool.onUpload({ success: true, file: { media: 'last.pdf', title: 'Last' } })
    expect(tool.save(holder)).toEqual({
      title: 'Last',
      file: { media: 'last.pdf', size: 0 },
    })
  })
})

describe('Attaches – picking a file', () => {
  it('hands the Select and Upload clicks to the admin callbacks, with the block', () => {
    const { tool, config } = attachesWith('')
    const [select, upload] =
      tool.nodes.fileButton.querySelectorAll<HTMLElement>('.cdx-button')

    select!.click()
    expect(config.onSelectFile).toHaveBeenCalledWith(tool, expect.any(Event))
    expect(config.onUploadFile).not.toHaveBeenCalled()

    upload!.click()
    expect(config.onUploadFile).toHaveBeenCalledWith(tool, expect.any(Event))
  })
})

describe('Attaches – the inline uploader', () => {
  it('takes any file type, an attachment is not a picture', () => {
    expect(attachesWith('').tool.uploadAccept).toBe('')
  })
})

describe('Attaches – an older block', () => {
  it('takes the media name from the file url pw:block:upgrade kept', () => {
    const legacy = {
      title: 'Doc',
      file: { url: 'https://example.com/file.pdf', name: 'document.pdf', size: 1024 },
    }

    expect(Attaches.normalizeData(legacy)).toEqual({
      title: 'Doc',
      file: { media: 'file.pdf', size: 1024 },
    })
  })
})

describe('Attaches markdown round trip', () => {
  function importAttaches(markdown: string): { data: any; tunes: any } {
    let saved: { data: any; tunes: any } | null = null
    const editor = {
      blocks: {
        insert: () => ({ id: 'attaches-id' }),
        update: (_id: string, data: any, tunes: any) => {
          saved = { data, tunes }
        },
      },
    } as unknown as API

    Attaches.importFromMarkdown(editor, markdown)

    return saved!
  }

  it('reads the size and the anchor back from the arguments its export writes', () => {
    const markdown = Attaches.exportToMarkdown(
      { title: 'Report', file: { media: 'report.pdf', size: 2048 } },
      { anchor: 'files' },
    )
    const { data, tunes } = importAttaches(markdown)

    expect(data.file.size).toBe(2048)
    expect(tunes.anchor).toBe('files')
    expect(Attaches.exportToMarkdown(data, tunes)).toBe(markdown)
  })
})
