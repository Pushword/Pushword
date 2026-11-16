import { afterEach, expect, it, vi } from 'vitest'
import type EditorJS from '@editorjs/editorjs'
import { setupBlockInsertion } from './BlockInsertion'

let listeners: AbortController
afterEach(() => {
  listeners.abort()
  document.body.replaceChildren()
})

function setup(names: string[], readOnly = false) {
  listeners = new AbortController()
  const holder = document.createElement('div')
  const redactor = document.createElement('div')
  redactor.className = 'codex-editor__redactor'
  holder.append(redactor)
  document.body.append(holder)
  const blocks = names.map((name, index) => {
    const element = document.createElement('div')
    element.className = 'ce-block'
    element.dataset.id = `${index}`
    element.innerHTML = '<div class="ce-block__content"><div>Tool content</div></div>'
    redactor.append(element)
    return { name, id: `${index}`, holder: element }
  })
  const insert = vi.fn(() => ({ id: 'paragraph' }))
  const caret = vi.fn()
  const state = { isEnabled: readOnly }
  const editor = {
    readOnly: state,
    blocks: {
      getBlocksCount: () => blocks.length,
      getBlockByIndex: (index: number) => blocks[index],
      getBlockIndex: (id: string) => blocks.findIndex(block => block.id === id),
      insert,
    },
    caret: { setToBlock: caret },
  } as unknown as EditorJS
  setupBlockInsertion(editor, holder, 'Add paragraph', listeners.signal)
  return { holder, redactor, blocks, insert, caret, state }
}

it.each([
  'attaches', 'card_list', 'codeBlock', 'delimiter', 'embed', 'gallery',
  'groupEnd', 'groupStart', 'image', 'notice', 'pages_list', 'quiz',
  'quote', 'raw', 'snippet', 'table',
])('offers insertion between raw and %s, in both directions', type => {
  const { holder } = setup(['raw', type, 'raw'])
  const buttons = holder.querySelectorAll('.pw-block-insertion')
  expect(buttons).toHaveLength(2)
  expect(buttons[0]!.closest('.ce-block')!.getAttribute('data-id')).toBe('1')
  expect(buttons[1]!.closest('.ce-block')!.getAttribute('data-id')).toBe('2')
})

it.each(['paragraph', 'header', 'list'])('does not offer insertion beside %s', type => {
  const { holder } = setup(['raw', type, 'raw'])
  expect(holder.querySelector('.pw-block-insertion')).toBeNull()
})

it('does not add leading, trailing or duplicate controls', async () => {
  const { holder, redactor } = setup(['raw', 'table'])
  redactor.append(document.createTextNode(''))
  await new Promise(resolve => setTimeout(resolve, 0))
  expect(holder.querySelectorAll('.pw-block-insertion')).toHaveLength(1)
  expect(holder.querySelector('button')!.getAttribute('aria-label')).toBe('Add paragraph')
})

it('inserts at the live position after moving a block, clears selection, and focuses the paragraph', async () => {
  const { holder, redactor, blocks, insert, caret } = setup(['raw', 'table', 'quote'])
  const moved = blocks.splice(1, 1)[0]!
  blocks.unshift(moved)
  redactor.prepend(moved.holder)
  await new Promise(resolve => setTimeout(resolve, 0))
  const raw = blocks[1]!
  raw.holder.classList.add('ce-block--selected')
  raw.holder.querySelector<HTMLButtonElement>('.pw-block-insertion')!.click()
  expect(insert).toHaveBeenCalledExactlyOnceWith('paragraph', {}, undefined, 1, false)
  expect(caret).toHaveBeenCalledWith('paragraph', 'start')
  expect(holder.querySelector('.ce-block--selected')).toBeNull()
})

it('removes obsolete controls after a text block is moved between two other blocks', async () => {
  const { holder, redactor, blocks } = setup(['raw', 'table', 'paragraph'])
  const paragraph = blocks.splice(2, 1)[0]!
  blocks.splice(1, 0, paragraph)
  redactor.insertBefore(paragraph.holder, blocks[2]!.holder)
  await new Promise(resolve => setTimeout(resolve, 0))
  expect(holder.querySelector('.pw-block-insertion')).toBeNull()
})

it('does not offer insertion in read-only mode', () => {
  const { holder } = setup(['raw', 'table'], true)
  expect(holder.querySelector('.pw-block-insertion')).toBeNull()
})

it('refuses insertion when read-only mode starts before the click', () => {
  const { holder, state, insert } = setup(['raw', 'table'])
  state.isEnabled = true
  holder.querySelector<HTMLButtonElement>('button')!.click()
  expect(insert).not.toHaveBeenCalled()
})

it('removes the controls and disconnects when the editor is destroyed', async () => {
  const { holder, redactor } = setup(['raw', 'table'])
  listeners.abort()
  redactor.append(document.createTextNode(''))
  await new Promise(resolve => setTimeout(resolve, 0))
  expect(holder.querySelector('.pw-block-insertion')).toBeNull()
})
