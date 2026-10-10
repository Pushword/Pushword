import { describe, it, expect, vi, beforeEach } from 'vitest'
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

/**
 * Every image block reaches the picker through the same hidden <select>, so the
 * message a pick answers with carries that select's id — nothing tells the blocks
 * apart. A pick the editor abandons must therefore stop listening, or the next
 * one fills the abandoned block too.
 */
const FIELD_ID = 'editorjs_1_inline_image'
const MODAL_URL = '/admin/media?pwMediaPicker=1'

function pickerSelect(): void {
  document.body.innerHTML = `
    <div class="pw-media-picker">
      <select id="${FIELD_ID}" data-pw-media-picker-modal-url="${MODAL_URL}"></select>
      <button data-pw-media-picker-action="choose"></button>
    </div>
  `
}

function pickerPosts(data: Record<string, unknown>): void {
  window.dispatchEvent(
    new MessageEvent('message', { origin: window.location.origin, data }),
  )
}

function pickerSends(fileName: string): void {
  pickerPosts({
    type: 'pw-media-picker-select',
    fieldId: FIELD_ID,
    media: { id: 7, fileName },
  })
}

function pickerSendsMany(...fileNames: string[]): void {
  pickerPosts({
    type: 'pw-media-picker-multi-select',
    fieldId: FIELD_ID,
    items: fileNames.map((fileName, id) => ({ id, fileName })),
  })
}

describe('editorJsHelper.abstractOn', () => {
  beforeEach(pickerSelect)

  it('fills the block that asked for the media', () => {
    const tool = { onUpload: vi.fn(), handleUploadError: vi.fn() }

    editorJsHelper.abstractOn(tool, new Event('click'))
    pickerSends('photo.jpg')

    expect(tool.onUpload).toHaveBeenCalledWith(
      expect.objectContaining({ file: expect.objectContaining({ media: 'photo.jpg' }) }),
    )
  })

  it('leaves an abandoned pick out of the next selection', () => {
    const abandoned = { onUpload: vi.fn(), handleUploadError: vi.fn() }
    const picking = { onUpload: vi.fn(), handleUploadError: vi.fn() }

    // The editor opens the picker, closes it without choosing, then picks for another block
    editorJsHelper.abstractOn(abandoned, new Event('click'))
    editorJsHelper.abstractOn(picking, new Event('click'))
    pickerSends('photo.jpg')

    expect(abandoned.onUpload).not.toHaveBeenCalled()
    expect(picking.onUpload).toHaveBeenCalledOnce()
  })

  it('stops listening once its pick lands', () => {
    const tool = { onUpload: vi.fn(), handleUploadError: vi.fn() }

    editorJsHelper.abstractOn(tool, new Event('click'))
    pickerSends('photo.jpg')
    pickerSends('other.jpg')

    expect(tool.onUpload).toHaveBeenCalledOnce()
  })
})

describe('editorJsHelper.abstractOnMulti', () => {
  beforeEach(pickerSelect)

  it('hands the whole selection to the block that asked for it', () => {
    const tool = { onMultiUpload: vi.fn() }

    editorJsHelper.abstractOnMulti(tool, new Event('click'))
    pickerSendsMany('one.jpg', 'two.jpg')

    expect(tool.onMultiUpload).toHaveBeenCalledWith([
      expect.objectContaining({ media: 'one.jpg' }),
      expect.objectContaining({ media: 'two.jpg' }),
    ])
  })

  it('leaves an abandoned pick out of the next selection', () => {
    const abandoned = { onMultiUpload: vi.fn() }
    const picking = { onMultiUpload: vi.fn() }

    editorJsHelper.abstractOnMulti(abandoned, new Event('click'))
    editorJsHelper.abstractOnMulti(picking, new Event('click'))
    pickerSendsMany('one.jpg')

    expect(abandoned.onMultiUpload).not.toHaveBeenCalled()
    expect(picking.onMultiUpload).toHaveBeenCalledOnce()
  })

  it('restores the base url it borrowed to open the picker in multi mode', () => {
    const select = document.querySelector('select') as HTMLSelectElement

    editorJsHelper.abstractOnMulti({ onMultiUpload: vi.fn() }, new Event('click'))

    expect(select.dataset.pwMediaPickerModalUrl).toBe(MODAL_URL)
  })
})
