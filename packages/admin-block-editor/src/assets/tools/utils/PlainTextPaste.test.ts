// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type EditorJS from '@editorjs/editorjs'
import { setupPlainTextPaste } from './PlainTextPaste'
import { textSelections } from './TextSelectionState'

describe('plain text paste', () => {
  let controller: AbortController
  let holder: HTMLElement
  let heading: HTMLElement
  let editor: EditorJS
  let insert: ReturnType<typeof vi.fn<typeof document.execCommand>>

  beforeEach(() => {
    document.body.innerHTML = '<div id="editor"><h2 contenteditable="true">Title</h2></div>'
    holder = document.getElementById('editor')!
    heading = holder.querySelector('h2')!
    editor = { readOnly: { isEnabled: false } } as EditorJS
    controller = new AbortController()
    insert = vi.fn(() => true)
    document.execCommand = insert
    setupPlainTextPaste(editor, holder, controller.signal)
  })

  afterEach(() => controller.abort())

  function shortcut(target = heading, modifiers: KeyboardEventInit = { ctrlKey: true, shiftKey: true }): void {
    target.dispatchEvent(new KeyboardEvent('keydown', { key: 'V', bubbles: true, ...modifiers }))
  }

  function paste(target = heading, text = '- Une agence au service des guides et des voyageurs', types = ['text/plain', 'text/html']): Event {
    const event = new Event('paste', { bubbles: true, cancelable: true })
    Object.defineProperty(event, 'clipboardData', {
      value: { types, getData: (type: string) => type === 'text/plain' ? text : '<ul><li>Formatted text</li></ul>' },
    })
    target.dispatchEvent(event)
    return event
  }

  it.each(['ctrlKey', 'metaKey'])('inserts literal text with %s+Shift+V before other paste handlers', (modifier) => {
    const downstream = vi.fn()
    document.addEventListener('paste', downstream, { capture: true, signal: controller.signal })
    shortcut(heading, { [modifier]: true, shiftKey: true })

    expect(paste().defaultPrevented).toBe(true)
    expect(insert).toHaveBeenCalledExactlyOnceWith('insertText', false, '- Une agence au service des guides et des voyageurs')
    expect(downstream).not.toHaveBeenCalled()
  })

  it('consumes the shortcut so the next normal paste can import Markdown', () => {
    shortcut()
    paste()
    expect(paste().defaultPrevented).toBe(false)
    expect(insert).toHaveBeenCalledTimes(1)
  })

  it.each(['keyup', 'blur', 'keydown'])('clears the shortcut on %s', (type) => {
    shortcut()
    heading.dispatchEvent(type === 'keydown'
      ? new KeyboardEvent(type, { key: 'a', bubbles: true })
      : new Event(type, { bubbles: true }))
    expect(paste().defaultPrevented).toBe(false)
    expect(insert).not.toHaveBeenCalled()
  })

  it('leaves normal Ctrl+V to the existing handlers', () => {
    shortcut(heading, { ctrlKey: true })
    expect(paste().defaultPrevented).toBe(false)
    expect(insert).not.toHaveBeenCalled()
  })

  it.each(['input', 'textarea', 'div'])('leaves a native or noneditable %s alone', (tag) => {
    const target = holder.appendChild(document.createElement(tag))
    shortcut(target)
    expect(paste(target).defaultPrevented).toBe(false)
    expect(insert).not.toHaveBeenCalled()
  })

  it('ignores shortcuts and pastes outside this editor', () => {
    const other = document.body.appendChild(document.createElement('div'))
    other.contentEditable = 'true'
    shortcut(other)
    expect(paste(heading).defaultPrevented).toBe(false)
    shortcut()
    expect(paste(other).defaultPrevented).toBe(false)
    expect(insert).not.toHaveBeenCalled()
  })

  it.each([
    '<span contenteditable="false">Control</span>',
    '<div class="monaco-editor" contenteditable="true">Code</div>',
    '<div class="editorjs-monaco-wrapper"><div contenteditable="true">Code</div></div>',
  ])('leaves noneditable controls and code editors alone: %s', (html) => {
    heading.innerHTML = html
    const target = heading.querySelector<HTMLElement>('[contenteditable]')!
    shortcut(target)
    expect(paste(target).defaultPrevented).toBe(false)
    expect(insert).not.toHaveBeenCalled()
  })

  it('leaves a read-only editor alone', () => {
    editor.readOnly.isEnabled = true
    shortcut()
    expect(paste().defaultPrevented).toBe(false)
    expect(insert).not.toHaveBeenCalled()
  })

  it('leaves clipboard files alone', () => {
    shortcut()
    expect(paste(heading, '', ['Files']).defaultPrevented).toBe(false)
    expect(insert).not.toHaveBeenCalled()
  })

  it('removes its listeners when the editor is destroyed', () => {
    shortcut()
    controller.abort()
    expect(paste().defaultPrevented).toBe(false)
    expect(insert).not.toHaveBeenCalled()
  })

  it('replaces a stored selection across paragraphs when pasting plain text', () => {
    holder.innerHTML = ['Before selected', 'selected after'].map((text, index) =>
      `<div class="ce-block" data-id="${index}"><div class="ce-block__content"><div contenteditable="true" class="ce-paragraph">${text}</div></div></div>`).join('')
    const blocks = () => Array.from(holder.querySelectorAll<HTMLElement>('.ce-block'))
    editor.blocks = {
      getBlockIndex: (id: string) => blocks().findIndex(block => block.dataset.id === id),
      getBlockByIndex: () => ({ name: 'paragraph' }),
      delete: (index: number) => blocks()[index]!.remove(),
    } as unknown as EditorJS['blocks']
    const fields = holder.querySelectorAll<HTMLElement>('.ce-paragraph')
    const range = document.createRange()
    range.setStart(fields[0]!.firstChild!, 'Before '.length)
    range.setEnd(fields[1]!.firstChild!, 'selected'.length)
    textSelections.set(holder, range)
    window.getSelection()!.collapse(range.startContainer, range.startOffset)
    shortcut(fields[0]!)

    expect(paste(fields[0]!, '<literal> - pasted').defaultPrevented).toBe(true)
    expect(Array.from(holder.querySelectorAll('.ce-paragraph'), field => field.textContent)).toEqual(['Before <literal> - pasted after'])
    expect(holder.querySelector('literal')).toBeNull()
    expect(textSelections.has(holder)).toBe(false)
    expect(insert).not.toHaveBeenCalled()
  })
})
