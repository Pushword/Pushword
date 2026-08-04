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

function attachesWith(media: string): { tool: Attaches; dispatchChange: () => void } {
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

  return { tool, dispatchChange }
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

describe('Attaches – the inline uploader', () => {
  it('takes any file type, an attachment is not a picture', () => {
    expect(attachesWith('').tool.uploadAccept).toBe('')
  })
})
