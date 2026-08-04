import { describe, it, expect, vi } from 'vitest'
import { editorJsHelper } from './editorJsHelper'

/**
 * The inline uploader replaces the media picker's upload button, which opened
 * the media form in a modal iframe. Here the file dialog is the whole flow.
 */

function fileInputBuiltBy(run: () => void): HTMLInputElement {
  const created: HTMLInputElement[] = []
  const createElement = document.createElement.bind(document)
  const spy = vi.spyOn(document, 'createElement').mockImplementation((tag: string) => {
    const element = createElement(tag)
    if (tag === 'input') {
      // The dialog cannot open under a test runner; the click is what we stub out.
      ;(element as HTMLInputElement).click = vi.fn()
      created.push(element as HTMLInputElement)
    }

    return element
  })

  run()
  spy.mockRestore()

  const input = created[0]
  if (!input) throw new Error('uploadInline() built no input')

  return input
}

describe('editorJsHelper.uploadInline', () => {
  it('opens a file dialog limited to what the tool accepts', () => {
    const tool = { uploadFile: vi.fn(), uploadAccept: 'image/*' }

    const input = fileInputBuiltBy(() => editorJsHelper.uploadInline(tool))

    expect(input.type).toBe('file')
    expect(input.accept).toBe('image/*')
    expect(input.click).toHaveBeenCalled()
  })

  it('leaves the input out of the document, since a cancelled dialog fires nothing', () => {
    const tool = { uploadFile: vi.fn(), uploadAccept: '' }

    const input = fileInputBuiltBy(() => editorJsHelper.uploadInline(tool))

    expect(input.isConnected).toBe(false)
    expect(input.accept).toBe('')
  })

  it('hands the picked file to the tool', () => {
    const tool = { uploadFile: vi.fn(), uploadAccept: '' }
    const input = fileInputBuiltBy(() => editorJsHelper.uploadInline(tool))
    const file = new File(['x'], 'photo.jpg', { type: 'image/jpeg' })

    Object.defineProperty(input, 'files', { value: [file] })
    input.dispatchEvent(new Event('change'))

    expect(tool.uploadFile).toHaveBeenCalledWith(file)
  })

  it('does nothing when the editor picks no file', () => {
    const tool = { uploadFile: vi.fn(), uploadAccept: '' }
    const input = fileInputBuiltBy(() => editorJsHelper.uploadInline(tool))

    Object.defineProperty(input, 'files', { value: [] })
    input.dispatchEvent(new Event('change'))

    expect(tool.uploadFile).not.toHaveBeenCalled()
  })
})
