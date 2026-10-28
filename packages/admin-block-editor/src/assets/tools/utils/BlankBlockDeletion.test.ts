import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import type EditorJS from '@editorjs/editorjs'
import { setupBlankBlockDeletion } from './BlankBlockDeletion'

let holder: HTMLElement
let listeners: AbortController

beforeEach(() => {
  document.body.innerHTML = '<div id="editor"></div>'
  holder = document.getElementById('editor')!
  listeners = new AbortController()
})

afterEach(() => listeners.abort())

function setup(types = ['paragraph', 'header'], texts = ['&nbsp;', 'Title'], active = 0, key = 'Delete') {
  const blocks = types.map((name, index) => {
    const element = document.createElement('div')
    element.className = 'ce-block'
    element.dataset.id = String(index)
    element.innerHTML = name === 'header'
      ? `<select contenteditable="false"><option>H2</option><option>H3</option></select><h3 class="ce-header" contenteditable="true">${texts[index]}</h3>`
      : `<div class="ce-paragraph" contenteditable="true">${texts[index]}</div>`
    holder.append(element)
    return { id: String(index), name, holder: element }
  })
  const editor = {
    readOnly: { isEnabled: false },
    blocks: {
      getBlockIndex: (id: string) => Number(id),
      getBlocksCount: () => blocks.length,
      getBlockByIndex: (index: number) => blocks[index],
      delete: vi.fn(),
    },
    caret: { setToBlock: vi.fn() },
  }
  const field = blocks[active]!.holder.querySelector<HTMLElement>('[contenteditable="true"]')!
  const range = document.createRange()
  range.selectNodeContents(field)
  range.collapse(key === 'Backspace')
  window.getSelection()!.removeAllRanges()
  window.getSelection()!.addRange(range)
  setupBlankBlockDeletion(editor as unknown as EditorJS, holder, listeners.signal)
  const press = (init: KeyboardEventInit = {}) => {
    const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...init })
    field.dispatchEvent(event)
    return event
  }
  return { editor, field, blocks, press }
}

for (const blankType of ['paragraph', 'header']) {
  for (const key of ['Delete', 'Backspace']) {
    it.each(['', '<br>', ' ', '&nbsp;', '<b>&nbsp;</b>', '\u200b\u200b', '\uFEFF'])(
      `${key} removes a ${blankType} containing %j and retains the following heading`, text => {
        const { editor, press } = setup([blankType, 'header'], [text, 'Title'], key === 'Delete' ? 0 : 1, key)
        expect(press().defaultPrevented).toBe(true)
        expect(editor.blocks.delete).toHaveBeenCalledExactlyOnceWith(0)
        expect(editor.caret.setToBlock).toHaveBeenCalledExactlyOnceWith('1', 'start')
      },
    )
  }
}

it.each(['Delete', 'Backspace'])('removes a trailing empty heading on %s, retaining the previous block', key => {
  const { editor, press } = setup(['paragraph', 'header'], ['Text', ''], key === 'Delete' ? 0 : 1, key)
  expect(press().defaultPrevented).toBe(true)
  expect(editor.blocks.delete).toHaveBeenCalledExactlyOnceWith(1)
  expect(editor.caret.setToBlock).toHaveBeenCalledExactlyOnceWith('0', 'end')
})

it.each([
  { key: 'Enter' }, { shiftKey: true }, { ctrlKey: true }, { altKey: true },
  { metaKey: true }, { isComposing: true },
])('leaves shortcuts and composition alone: %j', init => {
  const { editor, press } = setup()
  expect(press(init).defaultPrevented).toBe(false)
  expect(editor.blocks.delete).not.toHaveBeenCalled()
})

it('leaves nonempty blocks to Editor.js for merging', () => {
  const { editor, press } = setup(['paragraph', 'header'], ['Text', 'Title'])
  expect(press().defaultPrevented).toBe(false)
  expect(editor.blocks.delete).not.toHaveBeenCalled()
})

it.each(['Delete', 'Backspace'])('leaves character deletion inside the field to the browser on %s', key => {
  const { editor, field, press } = setup(['header', 'paragraph'], ['', '&nbsp;&nbsp;'], 1, key)
  window.getSelection()!.collapse(field.firstChild, 1)
  expect(press().defaultPrevented).toBe(false)
  expect(editor.blocks.delete).not.toHaveBeenCalled()
})

it('treats trailing collapsible spaces as the end of the field, like Editor.js', () => {
  const { editor, field, press } = setup(['paragraph', 'header'], [' ', 'Title'])
  window.getSelection()!.collapse(field.firstChild, 0)
  expect(press().defaultPrevented).toBe(true)
  expect(editor.blocks.delete).toHaveBeenCalledExactlyOnceWith(0)
})

it.each(['Delete', 'Backspace'])('keeps the only block on %s', key => {
  const { editor, press } = setup(['header'], [''], 0, key)
  expect(press().defaultPrevented).toBe(false)
  expect(editor.blocks.delete).not.toHaveBeenCalled()
})

it.each(['Delete', 'Backspace'])('retains the following heading when both blocks are blank on %s', key => {
  const active = key === 'Delete' ? 0 : 1
  const { editor, press } = setup(['paragraph', 'header'], ['&nbsp;', ''], active, key)
  expect(press().defaultPrevented).toBe(true)
  expect(editor.blocks.delete).toHaveBeenCalledExactlyOnceWith(0)
  expect(editor.caret.setToBlock).toHaveBeenCalledExactlyOnceWith('1', 'start')
})

it('leaves the heading level selector to its native keyboard handling', () => {
  const { editor, blocks } = setup(['header', 'paragraph'], ['', 'Text'])
  const selector = blocks[0]!.holder.querySelector('select')!
  const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true })
  selector.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  expect(editor.blocks.delete).not.toHaveBeenCalled()
})

it.each(['text range', 'no range', 'outside range', 'read-only', 'selected blocks', 'destroyed'])(
  'leaves deletion alone with %s', condition => {
    const { editor, field, blocks, press } = setup()
    if (condition === 'text range') window.getSelection()!.selectAllChildren(field)
    if (condition === 'no range') window.getSelection()!.removeAllRanges()
    if (condition === 'outside range') window.getSelection()!.collapse(document.body, 0)
    if (condition === 'read-only') editor.readOnly.isEnabled = true
    if (condition === 'selected blocks') blocks[0]!.holder.classList.add('ce-block--selected')
    if (condition === 'destroyed') listeners.abort()
    expect(press().defaultPrevented).toBe(false)
    expect(editor.blocks.delete).not.toHaveBeenCalled()
  },
)

it('respects a Delete already handled by another listener', () => {
  holder.addEventListener('keydown', event => event.preventDefault(), true)
  const { editor, press } = setup()
  press()
  expect(editor.blocks.delete).not.toHaveBeenCalled()
})

it.each(['Delete', 'Backspace'])('does not treat an internal field boundary as a block boundary on %s', key => {
  const { editor, field, blocks, press } = setup(['header', 'quote', 'header'], ['', 'Quote', ''], 1, key)
  const caption = document.createElement('div')
  caption.contentEditable = 'true'
  caption.textContent = 'Caption'
  if (key === 'Delete') blocks[1]!.holder.append(caption)
  else blocks[1]!.holder.prepend(caption)
  expect(field).not.toBe(caption)
  expect(press().defaultPrevented).toBe(false)
  expect(editor.blocks.delete).not.toHaveBeenCalled()
})

it('does not delete other block types whose text happens to be empty', () => {
  const { editor, press } = setup(['quote', 'header'], ['', 'Title'], 1, 'Backspace')
  expect(press().defaultPrevented).toBe(false)
  expect(editor.blocks.delete).not.toHaveBeenCalled()
})
