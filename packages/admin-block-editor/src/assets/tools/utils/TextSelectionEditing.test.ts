import { afterEach, expect, it, vi } from 'vitest'
import { replaceTextSelection, textSelectionClipboard } from './TextSelectionContent'
import { textSelections } from './TextSelectionState'
import { embeddedEditors } from './Undo/Selection'

const p = (text: string) => `<div contenteditable="true" class="ce-paragraph">${text}</div>`
const item = (text: string, children = '', checked = false) => `<li class="cdx-list__item"><div class="cdx-list__checkbox ${checked ? 'cdx-list__checkbox--checked' : ''}"></div><div class="cdx-list__item-content" contenteditable="true">${text}</div>${children ? `<ul class="cdx-list__item-children">${children}</ul>` : ''}</li>`
const list = (items: string, style = 'unordered') => `<ul class="cdx-list cdx-list-${style}">${items}</ul>`
const cell = (text: string) => `<div class="tc-cell" contenteditable="true">${text}</div>`
const table = () => `<div class="tc-table"><div class="tc-row">${cell('CUT|AFTER')}${cell('OTHER')}</div><div class="tc-row">${cell('ROW')}${cell('LAST')}</div></div>`

function fixture(blocks: [string, string][]) {
  document.body.innerHTML = `<div id="editor"><div class="codex-editor__redactor">${blocks.map(([type, content], index) => `<div class="ce-block" data-id="${index}" data-type="${type}"><div class="ce-block__content">${content}</div></div>`).join('')}</div></div>`
  const holder = document.getElementById('editor')!
  const redactor = holder.firstElementChild!
  const all = () => Array.from(redactor.children) as HTMLElement[]
  const editor: any = {
    blocks: {
      getBlockIndex: (id: string) => all().findIndex(block => block.dataset.id === id),
      getBlockByIndex: (index: number) => ({ name: all()[index]!.dataset.type }),
      delete: (index: number) => all()[index]!.remove(),
      insert: (_type: string, data: { text: string }, _config: unknown, index: number) => {
        const block = document.createElement('div')
        block.className = 'ce-block'
        block.dataset.id = 'inserted'
        block.innerHTML = `<div class="ce-block__content">${p(data.text)}</div>`
        redactor.insertBefore(block, all()[index] ?? null)
        return { id: 'inserted' }
      },
    },
    caret: { setToBlock: vi.fn() },
  }
  const fields = () => Array.from(holder.querySelectorAll<HTMLElement>('[contenteditable]'))
  const range = (start = 0, end = fields().length - 1) => {
    const selected = document.createRange()
    const first = fields()[start]!
    const last = fields()[end]!
    selected.setStart(first.firstChild!, first.textContent!.indexOf('CUT'))
    selected.setEnd(last.firstChild!, last.textContent!.indexOf('AFTER'))
    return selected
  }
  return { holder, editor, fields, range, all,
    replace: (selection: Range, text = '') => replaceTextSelection(editor, holder, selection, text),
    clipboard: (selection: Range) => textSelectionClipboard(holder, selection),
  }
}

afterEach(() => document.body.replaceChildren())

it('keeps the paragraph prefix and the remaining list items', () => {
  const f = fixture([['paragraph', p('BEFORE|CUT')], ['list', list(item('CUT|AFTER') + item('OTHER'))]])
  f.replace(f.range(0, 1))
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|', 'AFTER', 'OTHER'])
  expect(f.all().map(block => block.dataset.type)).toEqual(['paragraph', 'list'])
})

it('keeps a list prefix and removes its selected following items before a paragraph', () => {
  const f = fixture([['list', list(item('BEFORE|CUT', item('CHILD CUT')) + item('CUT TOO'))], ['paragraph', p('CUT|AFTER')]])
  f.replace(f.range())
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|', 'AFTER'])
})

it('retains an empty parent when unselected children remain nested under it', () => {
  const f = fixture([['paragraph', p('BEFORE|CUT')], ['list', list(item('CUT', item('CUT|AFTER') + item('OTHER CHILD')) + item('OTHER'))]])
  f.replace(f.range(0, 2))
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|', '', 'AFTER', 'OTHER CHILD', 'OTHER'])
  expect(f.holder.querySelector('.cdx-list__item-children')!.textContent).toBe('AFTEROTHER CHILD')
})

it('joins sibling item boundaries and retains the last item children', () => {
  const f = fixture([['list', list(item('BEFORE|CUT', item('SELECTED CHILD')) + item('CUT|AFTER', item('KEPT CHILD')) + item('OTHER'))]])
  f.replace(f.range(0, 2))
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|AFTER', 'KEPT CHILD', 'OTHER'])
  expect(f.holder.querySelector('.cdx-list__item-children')!.textContent).toBe('KEPT CHILD')
})

it('keeps different nesting levels instead of promoting an unselected child', () => {
  const f = fixture([['list', list(item('BEFORE|CUT', item('CUT|AFTER', item('KEPT GRANDCHILD'))))]])
  f.replace(f.range(0, 1))
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|', 'AFTER', 'KEPT GRANDCHILD'])
  expect(f.holder.querySelectorAll('.cdx-list__item-children')).toHaveLength(2)
})

it('keeps checklist states on both retained boundary items', () => {
  const f = fixture([['list', list(item('BEFORE|CUT', '', true) + item('CUT|AFTER'), 'checklist')]])
  f.replace(f.range())
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|', 'AFTER'])
  expect(f.holder.querySelectorAll('.cdx-list__checkbox--checked')).toHaveLength(1)
})

it('preserves every table cell outside the selected part of the first cell', () => {
  const f = fixture([['paragraph', p('BEFORE|CUT')], ['table', table()]])
  f.replace(f.range(0, 1))
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|', 'AFTER', 'OTHER', 'ROW', 'LAST'])
  expect(f.holder.querySelectorAll('.tc-row')).toHaveLength(2)
})

it('clears selected table cells without removing rows or columns', () => {
  const f = fixture([['table', `<div class="tc-table"><div class="tc-row">${cell('BEFORE|CUT')}${cell('SELECTED')}${cell('CUT|AFTER')}${cell('OTHER')}</div></div>`]])
  f.replace(f.range(0, 2))
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|', '', 'AFTER', 'OTHER'])
})

it('preserves the caption outside the selected quote body', () => {
  const f = fixture([['paragraph', p('BEFORE|CUT')], ['quote', '<blockquote class="cdx-quote"><div class="cdx-quote__text" contenteditable="true">CUT|AFTER</div><div class="cdx-quote__caption" contenteditable="true">CAPTION</div></blockquote>']])
  f.replace(f.range(0, 1))
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|', 'AFTER', 'CAPTION'])
})

it('keeps a heading level and its unselected suffix', () => {
  const f = fixture([['paragraph', p('BEFORE|CUT')], ['header', '<h3 class="ce-header" contenteditable="true">CUT|AFTER</h3>']])
  f.replace(f.range())
  expect(f.holder.querySelector('h3')!.textContent).toBe('AFTER')
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|', 'AFTER'])
})

it.each(['delimiter', 'raw', 'image'])('removes a fully covered %s and joins paragraph boundaries', type => {
  const f = fixture([['paragraph', p('BEFORE|CUT')], [type, '<div>ATOMIC</div>'], ['paragraph', p('CUT|AFTER')]])
  f.replace(f.range())
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|AFTER'])
  expect(f.all()).toHaveLength(1)
})

it('keeps both markers when the selection crosses only the start of a group', () => {
  const f = fixture([['paragraph', p('BEFORE|CUT')], ['groupStart', '<div class="pw-group-start" data-pw-group-kind="div"></div>'], ['paragraph', p('CUT|AFTER')], ['groupEnd', '<div class="pw-group-end" data-pw-group-kind="div"></div>']])
  f.replace(f.range())
  expect(f.all().map(block => block.dataset.type)).toEqual(['paragraph', 'groupStart', 'paragraph', 'groupEnd'])
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|', 'AFTER'])
})

it('removes both markers of a fully selected group with its content', () => {
  const f = fixture([['paragraph', p('BEFORE|CUT')], ['groupStart', '<div class="pw-group-start" data-pw-group-kind="div"></div>'], ['paragraph', p('SELECTED')], ['groupEnd', '<div class="pw-group-end" data-pw-group-kind="div"></div>'], ['paragraph', p('CUT|AFTER')]])
  f.replace(f.range())
  expect(f.fields().map(field => field.textContent)).toEqual(['BEFORE|AFTER'])
  expect(f.all()).toHaveLength(1)
})

it('places replacement text before a paragraph suffix after an atomic starting block', () => {
  const f = fixture([['raw', '<div>ATOMIC</div>'], ['paragraph', p('CUT|AFTER')]])
  const range = f.range(0, 0)
  range.setStartBefore(f.all()[0]!)
  f.replace(range, 'typed')
  expect(f.fields().map(field => field.textContent)).toEqual(['typedAFTER'])
  expect(f.all()).toHaveLength(1)
})

it('replaces a wholly selected atomic block with one editable paragraph', () => {
  const f = fixture([['raw', '<div>ATOMIC</div>']])
  const range = document.createRange()
  range.selectNode(f.all()[0]!)
  f.replace(range, '<typed>')
  expect(f.all()).toHaveLength(1)
  expect(f.fields()[0]!.innerHTML).toBe('&lt;typed&gt;')
  expect(f.editor.caret.setToBlock).toHaveBeenCalledExactlyOnceWith('inserted', 'end')
})

it('copies partial list text with nesting, checklist state and line breaks', () => {
  const f = fixture([['list', list(item('BEFORE|CUT<br>line', item('CHILD', '', true)) + item('CUT|AFTER'), 'checklist')]])
  const clipboard = f.clipboard(f.range(0, 2))
  expect(clipboard['text/plain']).toBe('CUT\nline\nCHILD\nCUT|')
  expect(JSON.parse(clipboard['application/x-editor-js']!)[0].data.items).toEqual([
    { content: 'CUT<br>line', meta: { checked: false }, items: [{ content: 'CHILD', meta: { checked: true }, items: [] }] },
    { content: 'CUT|', meta: { checked: false }, items: [] },
  ])
})

it.each([0, 5])('copies an ordered list with its custom numbering starting at %s', start => {
  const f = fixture([['list', `<ol class="cdx-list" style="counter-reset: item ${start - 1}; --list-counter-type: upper-roman">${item('BEFORE|CUT')}${item('CUT|AFTER')}</ol>`]])
  const clipboard = f.clipboard(f.range())
  expect(clipboard['text/plain']).toBe('CUT\nCUT|')
  expect(clipboard['text/html']).toBe('<ol><li>CUT</li><li>CUT|</li></ol>')
  expect(JSON.parse(clipboard['application/x-editor-js']!)).toEqual([{
    tool: 'list',
    data: {
      style: 'ordered', meta: { start, counterType: 'upper-roman' },
      items: [
        { content: 'CUT', meta: {}, items: [] },
        { content: 'CUT|', meta: {}, items: [] },
      ],
    },
  }])
})

it('copies selected table cells as a table without leaking unselected cell text', () => {
  const f = fixture([['paragraph', p('BEFORE|CUT')], ['table', table()]])
  expect(f.clipboard(f.range(0, 1))).toEqual({
    'text/plain': 'CUT\n\nCUT|\t',
    'text/html': '<p>CUT</p><table><tr><td>CUT|</td><td></td></tr></table>',
  })
})

it('clears the stored range while keeping the insertion caret in a valid field', () => {
  const f = fixture([['paragraph', p('BEFORE|CUT')], ['header', '<h2 class="ce-header" contenteditable="true">CUT|AFTER</h2>']])
  const range = f.range()
  textSelections.set(f.holder, range)
  f.replace(range, '<')
  expect(textSelections.has(f.holder)).toBe(false)
  expect(f.fields()[0]!.innerHTML).toBe('BEFORE|&lt;')
  expect(getSelection()!.anchorNode!.textContent).toBe('<')
  expect(getSelection()!.anchorOffset).toBe(1)
})

it('leaves one unchecked list item when all item texts are removed', () => {
  const f = fixture([['list', list(item('CUT', '', true) + item('CUT|AFTER'), 'checklist')]])
  const range = f.range()
  range.setEnd(f.fields()[1]!, f.fields()[1]!.childNodes.length)
  f.replace(range)
  expect(f.fields().map(field => field.textContent)).toEqual([''])
  expect(f.holder.querySelector('.cdx-list__checkbox--checked')).toBeNull()
})

it('copies a fully selected image together with its caption', () => {
  const f = fixture([['image', '<img src="/media/photo.jpg"><div class="image-tool__caption" contenteditable="true">Caption</div>']])
  const range = document.createRange()
  range.selectNode(f.all()[0]!)
  expect(f.clipboard(range)).toEqual({ 'text/plain': 'Caption', 'text/html': '<img src="/media/photo.jpg" alt="Caption">' })
})

it('copies deferred code without requiring its embedded editor to be initialized', () => {
  const f = fixture([['raw', '<div class="editorjs-monaco-wrapper"></div>']])
  const wrapper = f.holder.querySelector<HTMLElement>('.editorjs-monaco-wrapper')!
  embeddedEditors.set(wrapper, { instance: undefined, activate: vi.fn(), getValue: () => '<b>Code</b>\nnext line' })
  const range = document.createRange()
  range.selectNode(f.all()[0]!)
  expect(f.clipboard(range)).toEqual({
    'text/plain': '<b>Code</b>\nnext line', 'text/html': '<pre><code>&lt;b&gt;Code&lt;/b&gt;\nnext line</code></pre>',
  })
})
