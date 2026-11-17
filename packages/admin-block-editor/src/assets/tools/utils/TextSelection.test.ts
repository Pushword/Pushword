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
  const blocks = () => Array.from(holder.querySelectorAll<HTMLElement>('.ce-block'))
  editor = { readOnly: { isEnabled: false }, inlineToolbar: { close: vi.fn() },
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
  document.body.replaceChildren()
})

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

it('clears a range before a click outside the editor', () => {
  select()
  document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
  expect(textSelections.has(holder)).toBe(false)
  expect(highlights.has('pw-text-selection')).toBe(false)
})
