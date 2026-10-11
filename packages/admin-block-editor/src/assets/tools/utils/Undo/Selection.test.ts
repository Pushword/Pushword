import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import type { editor as MonacoEditor } from 'monaco-editor'
import { captureSelection, embeddedEditors, restoreSelection } from './Selection'

let holder: HTMLElement
let editor: any
beforeEach(() => {
  document.body.innerHTML =
    '<div id="editor"><div class="ce-block" data-id="a"><div class="ce-block__content"><div contenteditable="true">First <b>bold</b> last</div><input value="Original"></div></div><div class="ce-block" data-id="b"><div class="ce-block__content"><div contenteditable="true">Second</div></div></div></div>'
  holder = document.getElementById('editor')!
  const blocks = Array.from(holder.querySelectorAll<HTMLElement>('.ce-block'))
  editor = {
    blocks: {
      getBlockIndex: (id: string) => {
        const i = blocks.findIndex((block) => block.dataset.id === id)
        return i < 0 ? undefined : i
      },
      getBlockByIndex: (i: number) => (blocks[i] ? { holder: blocks[i] } : undefined),
    },
    caret: { setToBlock: vi.fn() },
  }
})
afterEach(() => {
  document.body.replaceChildren()
  vi.restoreAllMocks()
})

it('restores a backward selection across inline elements and blocks', async () => {
  const a = holder.querySelector('b')!.firstChild!
  const b = holder.querySelectorAll('[contenteditable]')[1]!.firstChild!
  getSelection()!.setBaseAndExtent(b, 4, a, 1)
  const saved = captureSelection(holder)
  getSelection()!.removeAllRanges()
  await restoreSelection(editor, holder, saved)
  expect(getSelection()!.anchorNode).toBe(b)
  expect(getSelection()!.anchorOffset).toBe(4)
  expect(getSelection()!.focusNode).toBe(a)
  expect(getSelection()!.focusOffset).toBe(1)
})

it('restores a native input range and its direction', async () => {
  const input = holder.querySelector('input')!
  input.focus()
  input.setSelectionRange(1, 5, 'backward')
  const saved = captureSelection(holder)
  input.setSelectionRange(0, 0)
  input.blur()
  await restoreSelection(editor, holder, saved)
  expect(document.activeElement).toBe(input)
  expect([input.selectionStart, input.selectionEnd, input.selectionDirection]).toEqual([
    1,
    5,
    'backward',
  ])
})

it('ignores a stale block reference returned as undefined by the real Editor.js API', async () => {
  const input = holder.querySelector('input')!
  input.focus()
  input.setSelectionRange(1, 2)
  const saved = captureSelection(holder)!
  saved.control!.block = 'removed'
  await expect(restoreSelection(editor, holder, saved)).resolves.toBeUndefined()
  expect(editor.caret.setToBlock).not.toHaveBeenCalled()
})

it('falls back to a text offset when inline markup has been normalized', async () => {
  const field = holder.querySelector<HTMLElement>('[contenteditable]')!
  getSelection()!.setBaseAndExtent(
    field.querySelector('b')!.firstChild!,
    2,
    field.querySelector('b')!.firstChild!,
    2,
  )
  const saved = captureSelection(holder)
  field.textContent = 'First bold last'
  await restoreSelection(editor, holder, saved)
  expect(getSelection()!.anchorNode).toBe(field.firstChild)
  expect(getSelection()!.anchorOffset).toBe(8)
})

it('restores selected blocks and cancels smooth scrolling explicitly', async () => {
  const scroll = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
  await restoreSelection(editor, holder, { blocks: ['a', 'b'] })
  expect(holder.querySelectorAll('.ce-block--selected')).toHaveLength(2)
  expect(document.activeElement).toBe(holder.firstElementChild)
  expect(scroll).toHaveBeenCalledWith({ left: 0, top: 0, behavior: 'instant' })
})

it('activates a deferred Monaco editor before restoring its selection and scroll', async () => {
  const wrapper = document.createElement('div')
  wrapper.className = 'editorjs-monaco-wrapper'
  holder.firstElementChild!.append(wrapper)
  const monaco = {
    focus: vi.fn(),
    setSelection: vi.fn(),
    setScrollPosition: vi.fn(),
    getDomNode: () => null,
    getPosition: () => null,
  }
  let finish!: () => void
  const activation = new Promise<void>((resolve) => {
    finish = resolve
  })
  const embedded = {
    instance: undefined as MonacoEditor.IStandaloneCodeEditor | undefined,
    activate: vi.fn(async () => {
      await activation
      embedded.instance = monaco as unknown as MonacoEditor.IStandaloneCodeEditor
    }),
  }
  embeddedEditors.set(wrapper, embedded)
  const selection = {
    startLineNumber: 2,
    startColumn: 3,
    endLineNumber: 4,
    endColumn: 5,
    selectionStartLineNumber: 4,
    selectionStartColumn: 5,
    positionLineNumber: 2,
    positionColumn: 3,
  }
  const restored = restoreSelection(editor, holder, {
    monaco: { block: 'a', selection, scrollTop: 30, scrollLeft: 10 },
  })
  expect(embedded.activate).toHaveBeenCalledOnce()
  expect(monaco.focus).not.toHaveBeenCalled()
  finish()
  await restored
  expect(monaco.focus).toHaveBeenCalledOnce()
  expect(monaco.setSelection).toHaveBeenCalledExactlyOnceWith(selection)
  expect(monaco.setScrollPosition).toHaveBeenCalledExactlyOnceWith({
    scrollTop: 30,
    scrollLeft: 10,
  })
})

it.each([false, true])(
  'restores inline ranges after empty nodes disappear (backward: %s)',
  async (backward) => {
    const field = holder.querySelector<HTMLElement>('[contenteditable]')!
    field.innerHTML = '<code>Format</code> me'
    field.prepend(document.createTextNode(''))
    getSelection()!.setBaseAndExtent(field, backward ? 2 : 1, field, backward ? 1 : 2)
    const saved = captureSelection(holder)
    const serialized = field.innerHTML
    field.innerHTML = serialized
    await restoreSelection(editor, holder, saved)
    expect(getSelection()!.toString()).toBe('Format')
    expect(getSelection()!.anchorOffset).toBe(backward ? 6 : 0)
    expect(getSelection()!.focusOffset).toBe(backward ? 0 : 6)
  },
)
