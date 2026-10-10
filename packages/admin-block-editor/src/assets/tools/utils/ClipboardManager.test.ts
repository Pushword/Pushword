// @vitest-environment jsdom
import { describe, it, expect, beforeAll, beforeEach, vi } from 'vitest'
import { createRequire } from 'module'
import ClipboardManager from './ClipboardManager'
import { MarkdownUtils } from './MarkdownUtils'
import { BlockSources } from '../../BlockSources'
import GroupStart from '../Group/GroupStart'
import GroupEnd from '../Group/GroupEnd'
import Paragraph from '../Paragraph/Paragraph'
import List from '../List/List'
import Raw from '../Raw/Raw'
import Table from '../Table/plugin'
import Image from '../Image/Image'
import Gallery from '../Gallery/Gallery'
import Attaches from '../Attaches/Attaches'
import Quote from '../Quote/Quote'
import CodeBlock from '../CodeBlock/CodeBlock'

/**
 * Unit tests for the copy/paste pipeline. The handlers are private, so we reach
 * them through a cast; this keeps the tests close to the real call sites while
 * exercising the behaviours fixed in this package (multi-block table paste,
 * cell-level copy/paste, and the block-selection Ctrl+C shortcut).
 */

// The suite reaches the private copy/paste handlers directly, so the manager is
// typed as an open record: members and their return values surface as `any`,
// which is what a white-box test of internals needs.
type AnyCm = Record<string, any>

function newManager(): AnyCm {
  // The constructor only needs an object to hang listeners off; the editor API
  // is lazily accessed and stubbed per-test where a method actually uses it.
  return new ClipboardManager({ editor: {} as any }) as unknown as AnyCm
}

/** Build a detached Editor.js-style table block. */
function buildTableBlock(opts: { rows: string[][]; heading?: boolean }): HTMLElement {
  const block = document.createElement('div')
  block.className = 'ce-block'
  const table = document.createElement('div')
  table.className = 'tc-table' + (opts.heading ? ' tc-table--heading' : '')
  opts.rows.forEach((cells) => {
    const row = document.createElement('div')
    row.className = 'tc-row'
    cells.forEach((text) => {
      const cell = document.createElement('div')
      cell.className = 'tc-cell'
      cell.setAttribute('contenteditable', 'true')
      cell.innerHTML = text
      row.appendChild(cell)
    })
    table.appendChild(row)
  })
  block.appendChild(table)
  return block
}

describe('ClipboardManager – pure helpers', () => {
  let cm: AnyCm
  beforeEach(() => {
    document.body.innerHTML = ''
    cm = newManager()
  })

  describe('rejoinTableFragments', () => {
    it('keeps a table together when a blank line precedes the delimiter row', () => {
      const input = '| A | B |\n\n| --- | --- |\n| 1 | 2 |'
      expect(cm.rejoinTableFragments(input)).toBe('| A | B |\n| --- | --- |\n| 1 | 2 |')
    })

    it('drops a blank line that follows a block-attribute line', () => {
      const input = '{.table-sticky-header}\n\n| A | B |\n| --- | --- |'
      expect(cm.rejoinTableFragments(input)).toBe('{.table-sticky-header}\n| A | B |\n| --- | --- |')
    })

    it('leaves two distinct tables separated', () => {
      const input = '| A | B |\n| --- | --- |\n| 1 | 2 |\n\n| C | D |\n| --- | --- |\n| 3 | 4 |'
      // The blank line sits between a data row and a header row (neither a
      // delimiter), so the two tables stay separate.
      expect(cm.rejoinTableFragments(input)).toBe(input)
    })
  })

  describe('detectMarkdownPatterns', () => {
    it('detects a markdown table row', () => {
      expect(cm.detectMarkdownPatterns('| A | B |\n| --- | --- |')).toBe(true)
    })

    it('detects a heading', () => {
      expect(cm.detectMarkdownPatterns('## Title')).toBe(true)
    })

    it('returns false for plain single-line text', () => {
      expect(cm.detectMarkdownPatterns('just some words')).toBe(false)
    })

    it('returns false for multi-line plain text without markdown', () => {
      expect(cm.detectMarkdownPatterns('Name Status\nAlice OK\nBob KO')).toBe(false)
    })

    it('detects a raw HTML table so it gets routed to a block', () => {
      expect(cm.detectMarkdownPatterns('<table><tr><td>a</td></tr></table>')).toBe(true)
    })
  })

  describe('convertHtmlToMarkdown – tables kept as HTML', () => {
    it('preserves a pasted table verbatim instead of flattening it to pipes', () => {
      const result = cm.convertHtmlToMarkdown('<table><tr><th>Name</th><th>Status</th></tr></table>')
      expect(result).toContain('<table')
      expect(result).not.toContain('| Name |')
    })

    it('preserves a merged-cell table (colspan) rather than mangling its grid', () => {
      const result = cm.convertHtmlToMarkdown(
        '<table><tr><td colspan="2">a</td></tr><tr><td>b</td><td>c</td></tr></table>',
      )
      expect(result).toContain('colspan="2"')
    })

    it('removes executable markup from an external table before preserving it', () => {
      const result = cm.convertHtmlToMarkdown(
        '<table onmouseover="alert(1)"><tr><td><a href="javascript:alert(1)">x</a><img src="x" onerror="alert(1)"></td></tr></table>',
      )

      expect(result).toContain('<table')
      expect(result).not.toMatch(/onmouseover|onerror|javascript:/)
    })
  })
})

describe('ClipboardManager – isSelectionWithinOneField', () => {
  let cm: AnyCm
  beforeEach(() => {
    document.body.innerHTML = ''
    cm = newManager()
  })

  it('is true when the selection is inside a single cell', () => {
    const block = buildTableBlock({ rows: [['hello', 'world'], ['a', 'b']], heading: true })
    document.body.appendChild(block)
    const cell = block.querySelector('.tc-cell')!.firstChild!
    const range = document.createRange()
    range.setStart(cell, 0)
    range.setEnd(cell, 3)
    const sel = window.getSelection()!
    sel.removeAllRanges()
    sel.addRange(range)
    expect(cm.isSelectionWithinOneField(sel)).toBe(true)
  })

  it('is false when the selection spans multiple cells', () => {
    const block = buildTableBlock({ rows: [['hello', 'world'], ['a', 'b']], heading: true })
    document.body.appendChild(block)
    const cells = block.querySelectorAll('.tc-cell')
    const range = document.createRange()
    range.setStart(cells[0]!.firstChild!, 0)
    range.setEnd(cells[1]!.firstChild!, 2)
    const sel = window.getSelection()!
    sel.removeAllRanges()
    sel.addRange(range)
    expect(cm.isSelectionWithinOneField(sel)).toBe(false)
  })

  it('is true when the selection crosses inline formatting within one field', () => {
    document.body.innerHTML = '<div contenteditable="true">One <b>two</b> three</div>'
    const field = document.body.firstElementChild!
    const range = document.createRange()
    // From one text node to another: the common ancestor is the field itself.
    range.setStart(field.firstChild!, 2)
    range.setEnd(field.lastChild!, 3)
    const sel = window.getSelection()!
    sel.removeAllRanges()
    sel.addRange(range)
    expect(cm.isSelectionWithinOneField(sel)).toBe(true)
  })

  it('is false when nothing is selected', () => {
    const sel = window.getSelection()!
    sel.removeAllRanges()
    expect(cm.isSelectionWithinOneField(sel)).toBe(false)
  })
})

describe('ClipboardManager – copying whole blocks', () => {
  beforeAll(() => {
    const require = createRequire(import.meta.url)
    const prettier = require('../../../Resources/public/prettier/standalone.js')
    const plugin = require('../../../Resources/public/prettier/markdown.js')
    vi.spyOn(MarkdownUtils as any, 'loadPrettier').mockResolvedValue({ prettier, plugin })
    window.pageHost = ''
  })

  let write: ReturnType<typeof vi.fn>
  beforeEach(() => {
    document.body.innerHTML = ''
    window.getSelection()?.removeAllRanges()
    write = vi.fn(async () => {})
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { write },
    })
    vi.stubGlobal(
      'ClipboardItem',
      class {
        constructor(readonly items: Record<string, Blob | Promise<Blob>>) {}
      },
    )
  })

  /** A Ctrl+C keydown on `target`, its handlers spied on. */
  function ctrlC(target: Element): any {
    return {
      ctrlKey: true,
      metaKey: false,
      key: 'c',
      target,
      preventDefault: vi.fn(),
      stopImmediatePropagation: vi.fn(),
    }
  }

  interface CopiedBlock {
    id: string
    type: string
    data: any
    tunes?: any
    /** What the block shows: a copy must export the saved data, not read this. */
    dom: string
  }

  const paragraph = (id: string, text: string): CopiedBlock => ({
    id,
    type: 'paragraph',
    data: { text },
    dom: `<div class="ce-paragraph" contenteditable="true">${text}</div>`,
  })

  /** Editor.js holding `blocks`, rendered in its holder and saved by its saver. */
  function copyingManager(blocks: CopiedBlock[]) {
    const saved = blocks.map(({ dom: _dom, ...block }) => block)
    const editor = {
      saver: { save: async () => ({ blocks: saved }) },
      tools: {
        getBlockTools: () => [
          { name: 'paragraph', constructable: Paragraph },
          { name: 'list', constructable: List },
          { name: 'table', constructable: Table },
          { name: 'image', constructable: Image },
          { name: 'gallery', constructable: Gallery },
          { name: 'attaches', constructable: Attaches },
          { name: 'quote', constructable: Quote },
          { name: 'code', constructable: CodeBlock },
        ],
      },
    }
    const holder = document.createElement('div')
    holder.id = 'editorjs_x'
    for (const { id, dom } of blocks) {
      holder.insertAdjacentHTML(
        'beforeend',
        `<div class="ce-block" data-id="${id}"><div class="ce-block__content">${dom}</div></div>`,
      )
    }
    document.body.appendChild(holder)
    const cm = new ClipboardManager({ editor } as any) as unknown as AnyCm

    return { cm, editor, holder }
  }

  /** The text the last clipboard write holds once its export has landed. */
  async function copied(): Promise<string> {
    const [items] = write.mock.lastCall as [{ items: Record<string, Promise<Blob>> }[]]

    return (await items[0]!.items['text/plain']!).text()
  }

  /** Select every block, press Ctrl+C, and read the clipboard back. */
  async function copy(...blocks: CopiedBlock[]): Promise<string> {
    const { cm, holder } = copyingManager(blocks)
    for (const block of holder.querySelectorAll('.ce-block')) {
      block.classList.add('ce-block--selected')
    }
    const event = ctrlC(holder.querySelector('.ce-block')!)
    cm.handleCopyShortcut(event)
    expect(event.preventDefault).toHaveBeenCalled()

    return copied()
  }

  it("leaves the copy to Editor.js where the Clipboard API can't take it", () => {
    vi.stubGlobal('ClipboardItem', undefined)
    const { cm, holder } = copyingManager([paragraph('p', 'Hello')])
    holder.querySelector('.ce-block')!.classList.add('ce-block--selected')
    const event = ctrlC(holder.querySelector('.ce-block')!)

    cm.handleCopyShortcut(event)

    expect(event.preventDefault).not.toHaveBeenCalled()
    expect(write).not.toHaveBeenCalled()
  })

  it('reports a refused clipboard write instead of leaving it unhandled', async () => {
    const error = new Error('Document is not focused')
    write.mockRejectedValue(error)
    const logged = vi.spyOn(console, 'error').mockImplementation(() => {})

    await copy(paragraph('p', 'Hello')).catch(() => {})
    await vi.waitFor(() =>
      expect(logged).toHaveBeenCalledWith('Unable to copy the selected blocks', error),
    )
    logged.mockRestore()
  })

  it('writes a bold table cell as the export does, not as the cell HTML', async () => {
    const content = [
      ['<b>Name</b>', 'Status'],
      ['Alice', 'OK'],
    ]
    const dom = buildTableBlock({ rows: content, heading: true }).innerHTML
    const table = { id: 't', type: 'table', data: { content, withHeadings: true }, dom }

    expect(await copy(table)).toBe(
      '| **Name** | Status |\n| -------- | ------ |\n| Alice    | OK     |',
    )
  })

  it('escapes a pipe in a table cell, which would split the cell otherwise', async () => {
    const content = [
      ['Operator', 'Meaning'],
      ['a | b', 'or'],
    ]
    const dom = buildTableBlock({ rows: content, heading: true }).innerHTML
    const table = { id: 't', type: 'table', data: { content, withHeadings: true }, dom }

    expect(await copy(table)).toBe(
      '| Operator | Meaning |\n| -------- | ------- |\n| a \\| b   | or      |',
    )
  })

  it('writes an image as its media name, not as the preview URL', async () => {
    const dom =
      '<div class="image-tool__image"><img src="/media/md/logo.png"></div>' +
      '<div class="image-tool__caption">Logo</div>'
    const data = { media: 'logo.png', caption: 'Logo' }

    expect(await copy({ id: 'i', type: 'image', data, dom })).toBe('![Logo](logo.png)')
  })

  it('keeps a clickable gallery clickable', async () => {
    const dom =
      '<div class="cdxcarousel-wrapper"><div class="cdxcarousel-list">' +
      '<div class="cdxcarousel-item"><img src="/media/md/1.jpg"></div>' +
      '<div class="cdxcarousel-item"><img src="/media/md/2.jpg">' +
      '<div class="image-tool__caption">Two</div></div></div></div>'
    const data = { items: [{ media: '1.jpg' }, { media: '2.jpg', caption: 'Two' }] }
    const tunes = { clickableTune: { value: true } }

    expect(await copy({ id: 'g', type: 'gallery', data, tunes, dom })).toBe(
      '{{ gallery({"1.jpg":"","2.jpg":"Two"}, clickable: true) }}',
    )
  })

  it('quotes an attachment title holding an apostrophe, its size in bytes', async () => {
    const dom =
      '<div class="cdx-attaches"><a href="/media/offre.pdf"></a>' +
      '<div class="cdx-attaches__title">L\'offre</div>' +
      '<div class="cdx-attaches__size">12.06 ko</div></div>'
    // The shape the import gives an attachment: the path as written, the size in bytes.
    const data = { title: "L'offre", file: { media: '/media/offre.pdf', size: 12345 } }

    expect(await copy({ id: 'a', type: 'attaches', data, dom })).toBe(
      `{{ attaches("L'offre", '/media/offre.pdf', '12345' ) }}`,
    )
  })

  it('keeps every line of a quote and its caption', async () => {
    const dom =
      '<div class="cdx-quote"><div class="cdx-quote__text">First line<br>Second line</div>' +
      '<div class="cdx-quote__caption">Author</div></div>'
    const data = { text: 'First line<br>Second line', caption: 'Author' }

    expect(await copy({ id: 'q', type: 'quote', data, dom })).toBe(
      '> First line\n> Second line\n> — <cite>Author</cite>',
    )
  })

  it('carries the block tunes', async () => {
    const tunes = { anchor: 'intro', class: 'lead' }

    expect(await copy({ ...paragraph('p', 'Intro'), tunes })).toBe(
      '{#intro .lead}\nIntro',
    )
  })

  it('copies the whole code of a code block, not the lines Monaco rendered', async () => {
    const lines = Array.from({ length: 40 }, (_, index) => `echo ${index};`)
    // Monaco renders the visible lines only; the language picker sits outside its wrapper.
    const rendered = lines
      .slice(0, 3)
      .map((line) => `<div class="view-line">${line}</div>`)
    const dom =
      '<div class="pw-code-block"><label>Language<select><option selected>php</option>' +
      '</select></label><div class="editorjs-monaco-wrapper monaco-codeblock-wrapper">' +
      rendered.join('') +
      '</div></div>'
    const data = { language: 'php', html: lines.join('\n') }

    expect(await copy({ id: 'c', type: 'code', data, dom })).toBe(
      '```php\n' + lines.join('\n') + '\n```',
    )
  })

  it('copies a block left untouched as the markdown it was parsed from', async () => {
    const { cm, editor, holder } = copyingManager([paragraph('p', 'Intro')])
    BlockSources.reset(editor).record('p', '<!-- kept -->Intro')
    holder.querySelector('.ce-block')!.classList.add('ce-block--selected')

    cm.handleCopyShortcut(ctrlC(holder))

    expect(await copied()).toBe('<!-- kept -->Intro')
  })

  it('copies a block edited since its parse as edited, not as its source', async () => {
    const { cm, editor, holder } = copyingManager([paragraph('p', 'Intro')])
    BlockSources.reset(editor).record('p', '<!-- kept -->Intro')
    const block = holder.querySelector('.ce-block')!
    block.classList.add('ce-block--selected')
    cm.handleCopyShortcut(ctrlC(block))
    expect(await copied()).toBe('<!-- kept -->Intro')

    // Typed in, then Ctrl+C before Editor.js' debounced onChange reported it.
    editor.saver.save = async () => ({
      blocks: [{ id: 'p', type: 'paragraph', data: { text: 'Intro edited' } }],
    })
    cm.handleCopyShortcut(ctrlC(block))

    expect(await copied()).toBe('Intro edited')
  })

  it('hands the clipboard its item within the gesture, before the export lands', async () => {
    const { cm, editor, holder } = copyingManager([paragraph('p', 'Intro')])
    let land!: () => void
    const landed = new Promise<void>((resolve) => (land = resolve))
    const save = editor.saver.save
    editor.saver.save = async () => {
      await landed
      return save()
    }
    const block = holder.querySelector('.ce-block')!
    block.classList.add('ce-block--selected')

    cm.handleCopyShortcut(ctrlC(block))

    expect(write).toHaveBeenCalledOnce()
    land()
    expect(await copied()).toBe('Intro')
  })

  it('copies the blocks a text selection runs across, in order, and those only', async () => {
    const { cm, holder } = copyingManager([
      paragraph('a', 'One'),
      paragraph('b', 'Two'),
      paragraph('c', 'Three'),
    ])
    const [first, second] = holder.querySelectorAll('.ce-paragraph')
    const range = document.createRange()
    range.setStart(first!.firstChild!, 1)
    range.setEnd(second!.firstChild!, 2)
    window.getSelection()!.addRange(range)
    const event = { ...ctrlC(first!), clipboardData: { setData: vi.fn() } }

    cm.handleCopy(event)

    expect(event.preventDefault).toHaveBeenCalled()
    expect(await copied()).toBe('One\n\nTwo')
  })

  it('copies the selected blocks on the copy event, as plain text only', async () => {
    const { cm, holder } = copyingManager([
      paragraph('a', 'One'),
      paragraph('b', 'Two'),
      paragraph('c', 'Three'),
    ])
    const blocks = holder.querySelectorAll('.ce-block')
    blocks[0]!.classList.add('ce-block--selected')
    blocks[2]!.classList.add('ce-block--selected')
    // A block selection leaves no text range: the event targets the body.
    const event = { ...ctrlC(document.body), clipboardData: { setData: vi.fn() } }

    cm.handleCopy(event)

    expect(event.stopImmediatePropagation).toHaveBeenCalled()
    expect(await copied()).toBe('One\n\nThree')
    const [items] = write.mock.lastCall as [{ items: object }[]]
    expect(Object.keys(items[0]!.items)).toEqual(['text/plain'])
  })

  it('copies a word picked out of a paragraph, not the paragraph', () => {
    const { cm, editor, holder } = copyingManager([paragraph('p', 'One two three')])
    const save = vi.spyOn(editor.saver, 'save')
    const field = holder.querySelector('.ce-paragraph')!
    const range = document.createRange()
    range.setStart(field.firstChild!, 4)
    range.setEnd(field.firstChild!, 7)
    window.getSelection()!.addRange(range)
    const event = { ...ctrlC(field), clipboardData: { setData: vi.fn() } }

    cm.handleCopy(event)

    expect(save).not.toHaveBeenCalled()
    expect(event.clipboardData.setData).toHaveBeenCalledWith('text/plain', 'two')
  })

  it('copies the formatting of a phrase picked out of a paragraph', () => {
    const { cm, holder } = copyingManager([paragraph('p', 'One <b>two</b> three')])
    const field = holder.querySelector('.ce-paragraph')!
    const range = document.createRange()
    range.setStart(field.firstChild!, 2)
    range.setEnd(field.lastChild!, 3)
    window.getSelection()!.addRange(range)
    const event = { ...ctrlC(field), clipboardData: { setData: vi.fn() } }

    cm.handleCopy(event)

    expect(event.clipboardData.setData).toHaveBeenCalledWith('text/plain', 'e **two** th')
  })

  it('copies a word picked out of one list item, not the list', () => {
    const items = ['One two', 'Three'].map((content) => ({
      content,
      meta: {},
      items: [],
    }))
    const dom =
      '<ul class="cdx-list cdx-list-unordered">' +
      items
        .map(
          ({ content }) =>
            '<li class="cdx-list__item"><div class="cdx-list__item-content" ' +
            `contenteditable="true">${content}</div></li>`,
        )
        .join('') +
      '</ul>'
    const list = { id: 'l', type: 'list', data: { style: 'unordered', items }, dom }
    const { cm, editor, holder } = copyingManager([list])
    const save = vi.spyOn(editor.saver, 'save')
    const item = holder.querySelector('.cdx-list__item-content')!
    const range = document.createRange()
    range.setStart(item.firstChild!, 4)
    range.setEnd(item.firstChild!, 7)
    window.getSelection()!.addRange(range)
    const event = { ...ctrlC(item), clipboardData: { setData: vi.fn() } }

    cm.handleCopy(event)

    expect(save).not.toHaveBeenCalled()
    expect(event.clipboardData.setData).toHaveBeenCalledWith('text/plain', 'two')
  })

  it('copies a table whole when the selection runs across its cells', async () => {
    const content = [
      ['Name', 'Status'],
      ['Alice', 'OK'],
    ]
    const dom = buildTableBlock({ rows: content, heading: true }).innerHTML
    const table = { id: 't', type: 'table', data: { content, withHeadings: true }, dom }
    const { cm, holder } = copyingManager([table])
    const cells = holder.querySelectorAll('.tc-cell')
    const range = document.createRange()
    range.setStart(cells[2]!.firstChild!, 1)
    range.setEnd(cells[3]!.firstChild!, 1)
    window.getSelection()!.addRange(range)

    cm.handleCopy({ ...ctrlC(cells[2]!), clipboardData: { setData: vi.fn() } })

    expect(await copied()).toBe(
      '| Name  | Status |\n| ----- | ------ |\n| Alice | OK     |',
    )
  })

  it('copies a list whole when the selection runs across its items', async () => {
    const items = ['One', 'Two'].map((content) => ({ content, meta: {}, items: [] }))
    // As @editorjs/list renders it: each item's text is its own editable field.
    const dom =
      '<ul class="cdx-list cdx-list-unordered">' +
      items
        .map(
          ({ content }) =>
            '<li class="cdx-list__item"><div class="cdx-list__item-content" ' +
            `contenteditable="true">${content}</div></li>`,
        )
        .join('') +
      '</ul>'
    const list = { id: 'l', type: 'list', data: { style: 'unordered', items }, dom }
    const { cm, holder } = copyingManager([list])
    const [first, second] = holder.querySelectorAll('.cdx-list__item-content')
    const range = document.createRange()
    range.setStart(first!.firstChild!, 1)
    range.setEnd(second!.firstChild!, 1)
    window.getSelection()!.addRange(range)

    cm.handleCopy({ ...ctrlC(first!), clipboardData: { setData: vi.fn() } })

    expect(await copied()).toBe('- One\n- Two')
  })

  it('copies a selection inside one table cell as its text, not the whole table', () => {
    const content = [
      ['Name', 'Status'],
      ['Alice', 'OK'],
    ]
    const dom = buildTableBlock({ rows: content, heading: true }).innerHTML
    const table = { id: 't', type: 'table', data: { content, withHeadings: true }, dom }
    const { cm, editor, holder } = copyingManager([table])
    const save = vi.spyOn(editor.saver, 'save')
    const cell = holder.querySelectorAll('.tc-cell')[2]!
    const range = document.createRange()
    range.setStart(cell.firstChild!, 0)
    range.setEnd(cell.firstChild!, 3)
    window.getSelection()!.addRange(range)
    const event = { ...ctrlC(cell), clipboardData: { setData: vi.fn() } }

    cm.handleCopy(event)

    expect(save).not.toHaveBeenCalled()
    expect(event.clipboardData.setData).toHaveBeenCalledWith('text/plain', 'Ali')
  })

  it.each([
    ['Cmd+C', { ctrlKey: false, metaKey: true }],
    ['Ctrl+C with Caps Lock on', { key: 'C' }],
  ])('copies the selected blocks on %s', async (_name, keys) => {
    const { cm, holder } = copyingManager([paragraph('p', 'Intro')])
    const block = holder.querySelector('.ce-block')!
    block.classList.add('ce-block--selected')

    cm.handleCopyShortcut({ ...ctrlC(block), ...keys })

    expect(await copied()).toBe('Intro')
  })

  it('copies the selected blocks when the focus sits outside the editor', async () => {
    const { cm, holder } = copyingManager([paragraph('p', 'Intro')])
    holder.querySelector('.ce-block')!.classList.add('ce-block--selected')

    cm.handleCopyShortcut(ctrlC(document.body))

    expect(await copied()).toBe('Intro')
  })

  it('leaves a text selection to the copy event', () => {
    const { cm, holder } = copyingManager([paragraph('p', 'Intro')])
    const block = holder.querySelector('.ce-block')!
    block.classList.add('ce-block--selected')
    const range = document.createRange()
    range.selectNodeContents(block.querySelector('.ce-paragraph')!)
    window.getSelection()!.addRange(range)
    const event = ctrlC(block)

    cm.handleCopyShortcut(event)

    expect(write).not.toHaveBeenCalled()
    expect(event.preventDefault).not.toHaveBeenCalled()
  })

  it('leaves Ctrl+C alone when no block is selected', () => {
    const { cm, holder } = copyingManager([paragraph('p', 'Intro')])
    const event = ctrlC(holder)

    cm.handleCopyShortcut(event)

    expect(write).not.toHaveBeenCalled()
    expect(event.preventDefault).not.toHaveBeenCalled()
  })

  // Other keys act on a block selection too (a paste, typing over it): only the
  // copy shortcut is taken over.
  it.each([
    ['Ctrl+V', { key: 'v' }],
    ['a C typed without Ctrl', { ctrlKey: false }],
  ])('leaves %s alone while blocks are selected', (_name, keys) => {
    const { cm, holder } = copyingManager([paragraph('p', 'Intro')])
    const block = holder.querySelector('.ce-block')!
    block.classList.add('ce-block--selected')
    const event = { ...ctrlC(block), ...keys }

    cm.handleCopyShortcut(event)

    expect(write).not.toHaveBeenCalled()
    expect(event.preventDefault).not.toHaveBeenCalled()
  })
})

describe('ClipboardManager – handlePaste routing', () => {
  let cm: AnyCm
  beforeEach(() => {
    document.body.innerHTML = ''
    cm = newManager()
    window.getSelection()?.removeAllRanges()
  })

  function pasteEvent(plain: string, html = ''): any {
    return {
      clipboardData: { getData: (t: string) => (t === 'text/plain' ? plain : html) },
      preventDefault: vi.fn(),
      stopPropagation: vi.fn(),
    }
  }

  /** Put the caret inside a contenteditable within a `.ce-block__content`. */
  function caretInBlock(): HTMLElement {
    const block = document.createElement('div')
    block.className = 'ce-block'
    const content = document.createElement('div')
    content.className = 'ce-block__content'
    const editable = document.createElement('div')
    editable.contentEditable = 'true'
    editable.textContent = 'x'
    content.appendChild(editable)
    block.appendChild(content)
    document.body.appendChild(block)
    const range = document.createRange()
    range.setStart(editable.firstChild!, 1)
    range.collapse(true)
    const sel = window.getSelection()!
    sel.removeAllRanges()
    sel.addRange(range)
    return editable
  }

  it('uses the markdown plain text and never converts html (multi-block table copy)', () => {
    caretInBlock()
    const plain = 'Intro paragraph\n\n| A | B |\n| --- | --- |\n| 1 | 2 |'
    const html = '<p>Intro paragraph</p><br><br><div class="tc-table"></div>'
    const convert = vi.spyOn(cm, 'convertHtmlToMarkdown')
    const insert = vi.spyOn(cm, 'insertMarkdownAsBlocks').mockImplementation(() => {})

    cm.handlePaste(pasteEvent(plain, html))

    expect(convert).not.toHaveBeenCalled()
    expect(insert).toHaveBeenCalledWith(plain)
  })

  it('falls back to html conversion for external rich text without markdown', () => {
    caretInBlock()
    const plain = 'Name Status\nAlice OK'
    const html = '<table><tr><th>Name</th><th>Status</th></tr></table>'
    const convert = vi
      .spyOn(cm, 'convertHtmlToMarkdown')
      .mockReturnValue('| Name | Status |\n| --- | --- |')
    const insert = vi.spyOn(cm, 'insertMarkdownAsBlocks').mockImplementation(() => {})

    cm.handlePaste(pasteEvent(plain, html))

    expect(convert).toHaveBeenCalledOnce()
    expect(insert).toHaveBeenCalledWith('| Name | Status |\n| --- | --- |')
  })

  it('does not create blocks when pasting inside a table cell', () => {
    const block = buildTableBlock({ rows: [['A', 'B']], heading: true })
    const content = document.createElement('div')
    content.className = 'ce-block__content'
    content.appendChild(block.querySelector('.tc-table')!)
    block.appendChild(content)
    document.body.appendChild(block)
    const cell = content.querySelector('.tc-cell')!
    const range = document.createRange()
    range.setStart(cell.firstChild!, 0)
    range.collapse(true)
    const sel = window.getSelection()!
    sel.removeAllRanges()
    sel.addRange(range)

    const insert = vi.spyOn(cm, 'insertMarkdownAsBlocks').mockImplementation(() => {})
    cm.handlePaste(pasteEvent('| X | Y |\n| --- | --- |\n| 1 | 2 |'))

    expect(insert).not.toHaveBeenCalled()
  })
})

describe('ClipboardManager – pasting markdown as blocks', () => {
  /** Editor stub carrying the tool registry and recording what gets inserted. */
  function pastingManager(): { cm: AnyCm; inserted: string[] } {
    const inserted: string[] = []
    const editor = {
      tools: {
        getBlockTools: () => [
          { name: 'groupStart', constructable: GroupStart },
          { name: 'groupEnd', constructable: GroupEnd },
          { name: 'paragraph', constructable: Paragraph },
          { name: 'raw', constructable: Raw },
        ],
      },
      blocks: {
        insert: (type: string) => {
          inserted.push(type)
          return { id: `b${inserted.length}` }
        },
        update: () => {},
      },
    }

    return { cm: new ClipboardManager({ editor } as any) as unknown as AnyCm, inserted }
  }

  it('imports a pasted group as its two markers', () => {
    const { cm, inserted } = pastingManager()

    cm.insertMarkdownAsBlocks('<div id="faq">\n\ntext\n\n</div>')

    expect(inserted).toEqual(['groupStart', 'paragraph', 'groupEnd'])
  })

  it('keeps the closer of a pasted hand-written div raw, like its opener', () => {
    const { cm, inserted } = pastingManager()

    cm.insertMarkdownAsBlocks('<div style="color:red">\n\ntext\n\n</div>')

    expect(inserted).toEqual(['raw', 'paragraph', 'raw'])
  })

  it('pastes an escaped pipe back as the text of its table cell', () => {
    const updates: any[] = []
    const editor = {
      tools: {
        getBlockTools: () => [
          { name: 'table', constructable: Table },
          { name: 'paragraph', constructable: Paragraph },
        ],
      },
      blocks: {
        insert: () => ({ id: 't' }),
        update: (_id: string, data: any) => updates.push(data),
      },
    }
    const cm = new ClipboardManager({ editor } as any) as unknown as AnyCm

    cm.insertMarkdownAsBlocks('| Operator | Meaning |\n| --- | --- |\n| a \\| b | or |')

    expect(updates[0].content).toEqual([
      ['Operator', 'Meaning'],
      ['a | b', 'or'],
    ])
  })
})
