import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { setupBlockSelection } from './BlockSelection'
import { setupBlockCut } from './ClipboardManager'

let holder: HTMLElement
let field: HTMLElement
let editor: any
let listeners: AbortController
let originalClipboard: PropertyDescriptor | undefined

beforeEach(() => {
  document.body.innerHTML = '<div id="editor" class="editorjs-holder"><div class="ce-block ce-block--selected" data-id="0"><div class="ce-block__content"><p contenteditable="true">First</p></div></div><div class="ce-block ce-block--selected" data-id="1"><div class="ce-block__content"><p contenteditable="true">Second</p></div></div></div>'
  holder = document.getElementById('editor')!
  field = holder.querySelector('p')!
  field.focus()
  window.getSelection()!.collapse(field.firstChild, 0)
  editor = {
    readOnly: { isEnabled: false },
    blocks: {
      getBlockIndex: (id: string) => Number(id),
      insert: vi.fn(() => ({ id: 'replacement' })),
      delete: vi.fn(),
    },
    caret: { setToBlock: vi.fn() },
  }
  listeners = new AbortController()
  originalClipboard = Object.getOwnPropertyDescriptor(navigator, 'clipboard')
  Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined })
  setupBlockSelection(editor, holder, listeners.signal)
  setupBlockCut(editor, holder, listeners.signal)
})

afterEach(() => {
  listeners.abort()
  if (originalClipboard) Object.defineProperty(navigator, 'clipboard', originalClipboard)
  else delete (navigator as unknown as { clipboard?: unknown }).clipboard
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
  document.body.replaceChildren()
})

function useClipboard(write: (items: any[]) => Promise<void>): void {
  vi.stubGlobal('ClipboardItem', class {
    constructor(public items: Record<string, Promise<Blob>>) {}
  })
  Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { write } })
  editor.saver = { save: async () => ({ blocks: [
    { id: '0', type: 'paragraph', data: { text: 'First' } },
    { id: '1', type: 'paragraph', data: { text: 'Second' } },
  ] }) }
  editor.tools = { getBlockTools: () => [{ name: 'paragraph', constructable: { exportToMarkdown: (data: { text: string }) => data.text } }] }
}

it.each(['insertText', 'insertReplacementText'])('replaces selected blocks on native %s without a keydown', inputType => {
  const event = new InputEvent('beforeinput', { inputType, data: 'ê<&', bubbles: true, cancelable: true })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(true)
  expect(editor.blocks.insert).toHaveBeenCalledWith('paragraph', { text: 'ê&lt;&amp;' }, undefined, 0, false)
  expect(editor.blocks.delete.mock.calls).toEqual([[2], [1]])
})

it.each(['read-only', 'outside', 'text range', 'no blocks', 'uncancelable'])('leaves native input alone with %s', condition => {
  if (condition === 'read-only') editor.readOnly.isEnabled = true
  if (condition === 'no blocks') for (const block of holder.children) block.classList.remove('ce-block--selected')
  if (condition === 'text range') window.getSelection()!.setBaseAndExtent(field.firstChild!, 0, field.firstChild!, 3)
  const target = condition === 'outside' ? document.body : field
  const event = new InputEvent('beforeinput', { inputType: 'insertText', data: 'x', bubbles: true, cancelable: condition !== 'uncancelable' })
  target.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  expect(editor.blocks.insert).not.toHaveBeenCalled()
})

it('waits for composition to commit before replacing the selected blocks', () => {
  field.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true }))
  const key = new KeyboardEvent('keydown', { key: 'Process', isComposing: true, bubbles: true, cancelable: true })
  field.dispatchEvent(key)
  const input = new InputEvent('beforeinput', { inputType: 'insertCompositionText', data: 'ê', isComposing: true, bubbles: true })
  field.dispatchEvent(input)
  expect(key.defaultPrevented).toBe(false)
  expect(input.defaultPrevented).toBe(false)
  expect(editor.blocks.insert).not.toHaveBeenCalled()
  const end = new Event('compositionend', { bubbles: true })
  // Happy DOM does not expose CompositionEvent.data.
  Object.defineProperty(end, 'data', { value: 'ê' })
  field.dispatchEvent(end)
  expect(editor.blocks.insert).toHaveBeenCalledExactlyOnceWith('paragraph', { text: 'ê' }, undefined, 0, false)
})

it('keeps the selected blocks when composition is cancelled', () => {
  field.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true }))
  const end = new Event('compositionend', { bubbles: true })
  Object.defineProperty(end, 'data', { value: '' })
  field.dispatchEvent(end)
  expect(editor.blocks.insert).not.toHaveBeenCalled()
  expect(holder.querySelectorAll('.ce-block--selected')).toHaveLength(2)
})

it('replaces a block selection even when a focused native input retains a text range', () => {
  const input = field.appendChild(document.createElement('input'))
  input.value = 'Original'
  input.focus()
  input.setSelectionRange(0, 8)
  input.dispatchEvent(new InputEvent('beforeinput', { inputType: 'insertText', data: 'x', bubbles: true, cancelable: true }))
  expect(editor.blocks.insert).toHaveBeenCalledExactlyOnceWith('paragraph', { text: 'x' }, undefined, 0, false)
})

it.each([
  { key: 'x', ctrlKey: true },
  { key: 'x', metaKey: true },
  { key: 'Delete', shiftKey: true },
])('preserves the browser cut action and keeps Editor.js from consuming $key', init => {
  const downstream = vi.fn()
  document.addEventListener('keydown', downstream, { signal: listeners.signal })
  const event = new KeyboardEvent('keydown', { ...init, bubbles: true, cancelable: true })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  expect(downstream).not.toHaveBeenCalled()
  expect(editor.blocks.insert).not.toHaveBeenCalled()
})

it('keeps a native HTML and text fallback without Clipboard API support', () => {
  const data: Record<string, string> = {}
  const event = new Event('cut', { bubbles: true, cancelable: true })
  Object.defineProperty(event, 'clipboardData', { value: { setData: (type: string, value: string) => { data[type] = value } } })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(true)
  expect(data['text/plain']).toBe('First\n\nSecond')
  expect(data['text/html']).toContain('First')
  expect(editor.blocks.insert).toHaveBeenCalledExactlyOnceWith('paragraph', { text: '' }, undefined, 0, false)
  expect(editor.blocks.delete.mock.calls).toEqual([[2], [1]])
})

it('waits for the canonical clipboard export before removing the blocks', async () => {
  const write = vi.fn(async (items: any[]) => { await items[0].items['text/plain'] })
  useClipboard(write)
  const event = new Event('cut', { bubbles: true, cancelable: true })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(true)
  expect(write).toHaveBeenCalledOnce()
  expect(editor.blocks.insert).not.toHaveBeenCalled()
  await new Promise(resolve => setTimeout(resolve, 0))
  const [items] = write.mock.calls[0]!
  expect(await (await items[0].items['text/plain']).text()).toBe('First\n\nSecond')
  expect(editor.blocks.insert).toHaveBeenCalledExactlyOnceWith('paragraph', { text: '' }, undefined, 0, false)
  expect(editor.blocks.delete.mock.calls).toEqual([[2], [1]])
})

it('does not delete a later selection while a clipboard write is pending', async () => {
  let finish!: () => void
  useClipboard(() => new Promise<void>(resolve => { finish = resolve }))
  field.dispatchEvent(new Event('cut', { bubbles: true, cancelable: true }))
  holder.firstElementChild!.classList.remove('ce-block--selected')
  finish()
  await new Promise(resolve => setTimeout(resolve, 0))
  expect(editor.blocks.insert).not.toHaveBeenCalled()
})

it('keeps the blocks when the clipboard write fails', async () => {
  const error = new Error('Clipboard permission denied')
  useClipboard(() => Promise.reject(error))
  const log = vi.spyOn(console, 'error').mockImplementation(() => {})
  field.dispatchEvent(new Event('cut', { bubbles: true, cancelable: true }))
  await new Promise(resolve => setTimeout(resolve, 0))
  expect(log).toHaveBeenCalledWith('Unable to copy the selected blocks', error)
  expect(editor.blocks.insert).not.toHaveBeenCalled()
  expect(holder.querySelectorAll('.ce-block--selected')).toHaveLength(2)
})

it('removes the input and cut handlers when the editor is destroyed', () => {
  listeners.abort()
  const event = new InputEvent('beforeinput', { inputType: 'insertText', data: 'x', bubbles: true, cancelable: true })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  expect(editor.blocks.insert).not.toHaveBeenCalled()
})
