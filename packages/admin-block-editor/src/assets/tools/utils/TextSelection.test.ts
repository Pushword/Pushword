import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { setupTextSelection } from './TextSelection'
import { setupBlockSelection } from './BlockSelection'
import { textSelections } from './TextSelectionState'

let holder: HTMLElement
let listeners: AbortController
let editor: any
let highlights: Map<string, Set<Range>>

beforeEach(() => {
  document.body.innerHTML = '<div id="editor"><div class="codex-editor__redactor"><div class="ce-block" data-id="p"><div class="ce-block__content"><div class="ce-paragraph" contenteditable="true">BEFORE|CUT</div></div></div><div class="ce-block" data-id="l"><div class="ce-block__content"><ul class="cdx-list"><li class="cdx-list__item"><div class="cdx-list__item-content" contenteditable="true">CUT|AFTER</div></li><li class="cdx-list__item"><div class="cdx-list__item-content" contenteditable="true">OTHER</div></li></ul></div></div></div></div>'
  holder = document.getElementById('editor')!
  highlights = new Map()
  vi.stubGlobal('Highlight', class extends Set<Range> { constructor(...ranges: Range[]) { super(ranges) } })
  vi.stubGlobal('CSS', { highlights })
  Object.defineProperty(document, 'caretPositionFromPoint', { configurable: true, value: vi.fn() })
  const blocks = () => Array.from(holder.querySelectorAll<HTMLElement>('.ce-block'))
  editor = { readOnly: { isEnabled: false }, inlineToolbar: { close: vi.fn(), open: vi.fn() },
    blocks: { getBlockIndex: (id: string) => blocks().findIndex(block => block.dataset.id === id),
      getBlockByIndex: (index: number) => ({ name: blocks()[index]!.dataset.id === 'p' ? 'paragraph' : 'list' }),
      delete: vi.fn((index: number) => blocks()[index]!.remove()), insert: vi.fn() }, caret: { setToBlock: vi.fn() } }
  listeners = new AbortController()
  setupBlockSelection(editor, holder, listeners.signal)
  setupTextSelection(editor, holder, listeners.signal)
})
afterEach(() => {
  listeners.abort()
  vi.unstubAllGlobals()
  Reflect.deleteProperty(document, 'caretPositionFromPoint')
  document.body.replaceChildren()
})

function caretPosition(offsetNode: Node, offset: number): CaretPosition {
  return { offsetNode, offset, getClientRect: () => null }
}

function select(): HTMLElement {
  const field = holder.querySelector<HTMLElement>('.ce-paragraph')!
  field.focus()
  const end = holder.querySelector('.cdx-list__item-content')!
  getSelection()!.setBaseAndExtent(field.firstChild!, 7, end.firstChild!, 4)
  document.dispatchEvent(new Event('selectionchange'))
  return field
}

it('keeps one precise range and clears competing block highlights', () => {
  holder.querySelectorAll('.ce-block').forEach(block => block.classList.add('ce-block--selected'))
  select()
  expect(holder.querySelector('.ce-block--selected')).toBeNull()
  expect(Array.from(highlights.get('pw-text-selection')!, range => range.toString())).toEqual(['CUT', 'CUT|'])
  expect(getSelection()!.isCollapsed).toBe(true)
  expect(textSelections.get(holder)!.toString()).toContain('CUTCUT|')
})

it.each(['Backspace', 'Delete'])('lets the precise range own %s before the block deletion handler', key => {
  const field = select()
  const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(true)
  expect(holder.querySelector('.ce-paragraph')!.textContent).toBe('BEFORE|')
  expect(Array.from(holder.querySelectorAll('.cdx-list__item-content'), field => field.textContent)).toEqual(['AFTER', 'OTHER'])
  expect(editor.blocks.delete).not.toHaveBeenCalled()
  expect(textSelections.has(holder)).toBe(false)
})

it('leaves read-only native selections available for copying', () => {
  editor.readOnly.isEnabled = true
  const field = select()
  expect(textSelections.has(holder)).toBe(false)
  expect(getSelection()!.isCollapsed).toBe(false)
  const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  expect(field.textContent).toBe('BEFORE|CUT')
})

it('releases its stored selection, highlight and handlers when the editor is destroyed', () => {
  const field = select()
  listeners.abort()
  expect(textSelections.has(holder)).toBe(false)
  expect(highlights.has('pw-text-selection')).toBe(false)
  const event = new InputEvent('beforeinput', { inputType: 'insertText', data: 'x', bubbles: true, cancelable: true })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  expect(field.textContent).toBe('BEFORE|CUT')
})

it('removes its whole-block highlight when the editor is destroyed', () => {
  const field = holder.querySelector<HTMLElement>('.ce-paragraph')!
  field.focus()
  getSelection()!.selectAllChildren(field)
  document.dispatchEvent(new Event('selectionchange'))
  expect(field.closest('.ce-block')!.classList.contains('pw-text-selection-block')).toBe(true)
  listeners.abort()
  expect(holder.querySelector('.pw-text-selection-block')).toBeNull()
})

it('clears a range before a click outside the editor', () => {
  select()
  document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
  expect(textSelections.has(holder)).toBe(false)
  expect(highlights.has('pw-text-selection')).toBe(false)
})

it('highlights all text in a paragraph as a block, including formatted endpoints', () => {
  const field = holder.querySelector<HTMLElement>('.ce-paragraph')!
  field.innerHTML = '<b>Before</b> selected <i>after</i>'
  field.focus()
  getSelection()!.setBaseAndExtent(field.querySelector('b')!.firstChild!, 0, field.querySelector('i')!.firstChild!, 5)
  document.dispatchEvent(new Event('selectionchange'))
  expect(field.closest('.ce-block')!.classList.contains('pw-text-selection-block')).toBe(true)
  expect(textSelections.has(holder)).toBe(false)
  expect(getSelection()!.toString()).toBe('Before selected after')
  getSelection()!.setBaseAndExtent(field.querySelector('b')!.firstChild!, 1, field.querySelector('i')!.firstChild!, 5)
  document.dispatchEvent(new Event('selectionchange'))
  expect(field.closest('.ce-block')!.classList.contains('pw-text-selection-block')).toBe(false)
})

it('keeps the pointer range when native dragging reports a range confined to the first field', () => {
  const first = holder.querySelector<HTMLElement>('.ce-paragraph')!
  const last = holder.querySelector<HTMLElement>('.cdx-list__item-content')!
  vi.mocked(document.caretPositionFromPoint).mockReturnValueOnce(caretPosition(first.firstChild!, 7))
    .mockReturnValue(caretPosition(last.firstChild!, 4))
  first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, button: 0 }))
  last.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, buttons: 1, cancelable: true }))
  getSelection()!.setBaseAndExtent(first.firstChild!, 7, first.firstChild!, 10)
  document.dispatchEvent(new Event('selectionchange'))
  expect(Array.from(highlights.get('pw-text-selection') ?? [], range => range.toString())).toEqual(['CUT', 'CUT|'])
  expect(textSelections.get(holder)!.endContainer).toBe(last.firstChild)
  expect(textSelections.get(holder)!.endOffset).toBe(4)
})

it('stops at the start of text when dragging over its insertion control', () => {
  const first = holder.querySelector<HTMLElement>('.ce-paragraph')!
  const last = holder.querySelector<HTMLElement>('.cdx-list__item-content')!
  const insertion = document.createElement('div')
  insertion.className = 'pw-block-insertion'
  last.closest('.ce-block__content')!.prepend(insertion)
  vi.spyOn(last, 'getBoundingClientRect').mockReturnValue({ top: 100, bottom: 120 } as DOMRect)
  vi.mocked(document.caretPositionFromPoint).mockReturnValueOnce(caretPosition(first.firstChild!, 7))
    .mockReturnValue(caretPosition(insertion, 0))
  first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, button: 0 }))
  insertion.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, buttons: 1, clientY: 95, cancelable: true }))
  expect(Array.from(highlights.get('pw-text-selection')!, range => range.toString())).toEqual(['CUT', ''])
  expect(holder.querySelector('.pw-text-selection-block')).toBeNull()
})

it('keeps a precise text endpoint when dragging in the block margin', () => {
  const first = holder.querySelector<HTMLElement>('.ce-paragraph')!
  const last = holder.querySelector<HTMLElement>('.cdx-list__item-content')!
  vi.spyOn(last, 'getBoundingClientRect').mockReturnValue({ top: 100, bottom: 120, left: 100, right: 200 } as DOMRect)
  const caret = vi.mocked(document.caretPositionFromPoint)
    .mockReturnValueOnce(caretPosition(first.firstChild!, 7))
    .mockReturnValueOnce(null)
    .mockReturnValue(caretPosition(last.firstChild!, 4))
  first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, button: 0 }))
  last.closest('.ce-block')!.dispatchEvent(new MouseEvent('mousemove', {
    bubbles: true, buttons: 1, clientX: 250, clientY: 110, cancelable: true,
  }))
  expect(caret).toHaveBeenLastCalledWith(199, 110)
  expect(Array.from(highlights.get('pw-text-selection')!, range => range.toString())).toEqual(['CUT', 'CUT|'])
})

it('does not highlight an image block when only its entire caption is selected', () => {
  const field = holder.querySelector<HTMLElement>('.ce-paragraph')!
  field.before(document.createElement('img'))
  field.focus()
  getSelection()!.selectAllChildren(field)
  document.dispatchEvent(new Event('selectionchange'))
  expect(holder.querySelector('.pw-text-selection-block')).toBeNull()
  expect(getSelection()!.toString()).toBe('BEFORE|CUT')
})

it.each(['deux mots', 'deux mots '])('opens the inline toolbar on mouseup after selecting "%s"', text => {
  const field = holder.querySelector<HTMLElement>('.ce-paragraph')!
  field.textContent = 'deux mots après'
  field.focus()
  vi.mocked(document.caretPositionFromPoint)
    .mockReturnValueOnce(caretPosition(field.firstChild!, 0))
    .mockReturnValue(caretPosition(field.firstChild!, text.length))
  field.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, button: 0 }))
  getSelection()!.setBaseAndExtent(field.firstChild!, 0, field.firstChild!, text.length)
  field.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }))
  expect(editor.inlineToolbar.open).toHaveBeenCalledOnce()
  expect(getSelection()!.toString()).toBe(text)
})

it('does not open the inline toolbar after a collapsed click or an unrelated mouseup', () => {
  const field = holder.querySelector<HTMLElement>('.ce-paragraph')!
  field.focus()
  vi.mocked(document.caretPositionFromPoint).mockReturnValue(caretPosition(field.firstChild!, 0))
  field.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, button: 0 }))
  getSelection()!.collapse(field.firstChild!, 0)
  field.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }))
  getSelection()!.selectAllChildren(field)
  document.body.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }))
  expect(editor.inlineToolbar.open).not.toHaveBeenCalled()
})

it('keeps the inline toolbar closed after releasing a cross-field selection', () => {
  const first = holder.querySelector<HTMLElement>('.ce-paragraph')!
  const last = holder.querySelector<HTMLElement>('.cdx-list__item-content')!
  vi.mocked(document.caretPositionFromPoint)
    .mockReturnValueOnce(caretPosition(first.firstChild!, 7))
    .mockReturnValue(caretPosition(last.firstChild!, 4))
  first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, button: 0 }))
  last.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, buttons: 1, cancelable: true }))
  last.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }))
  expect(textSelections.has(holder)).toBe(true)
  expect(editor.inlineToolbar.open).not.toHaveBeenCalled()
})
