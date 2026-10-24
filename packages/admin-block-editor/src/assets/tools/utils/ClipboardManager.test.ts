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
import Header from '../Header/Header'

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

    it.each(['{% endquiz %}', '{{ value }}', '{# comment #}'])('keeps the block following Twig %s separate', (twig) => {
      const input = `${twig}\n\nFollowing paragraph`
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
    it('preserves mixed nested lists from external rich text', () => {
      const html = '<ul><li>Parent<ol><li><b>Child</b><ul><li>Grandchild</li></ul></li></ol></li><li>Sibling</li></ul>'
      expect(cm.convertHtmlToMarkdown(html).trim()).toBe('- Parent\n  1. **Child**\n     - Grandchild\n- Sibling')
    })

    it('aligns nested ordered lists and continuation lines with a multi-digit parent item', () => {
      const items = Array.from({ length: 9 }, (_, index) => `<li>Parent ${index + 1}</li>`).join('')
      const html = `<ol>${items}<li>Parent 10<br>continued<ol><li>Child<br>continued<ul><li>Grandchild</li></ul></li></ol></li></ol>`
      const expected = [
        ...Array.from({ length: 10 }, (_, index) => `${index + 1}. Parent ${index + 1}`),
        '    continued',
        '    1. Child',
        '       continued',
        '       - Grandchild',
      ].join('\n')

      expect(cm.convertHtmlToMarkdown(html).trim()).toBe(expected)
    })

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

  it('keeps native HTML copy when asynchronous clipboard writing is unavailable', async () => {
    write.mockRejectedValue(new Error('Clipboard permission denied'))
    const writeText = vi.fn(async () => {})
    Object.defineProperty(navigator.clipboard, 'writeText', { configurable: true, value: writeText })
    const { cm, holder } = copyingManager([paragraph('p', 'One <b>two</b> three')])
    const field = holder.querySelector('.ce-paragraph')!
    const range = document.createRange()
    range.selectNodeContents(field)
    window.getSelection()!.addRange(range)
    const event = { ...ctrlC(field), clipboardData: { setData: vi.fn() } }

    cm.handleCopy(event)
    await new Promise(resolve => setTimeout(resolve, 0))

    expect(event.clipboardData.setData).toHaveBeenCalledWith('text/html', 'One <b>two</b> three')
    expect(writeText).not.toHaveBeenCalled()
  })

  it('reports a rejected block copy to the editor instead of only logging it', async () => {
    const { cm, holder } = copyingManager([paragraph('p', 'Copy me')])
    holder.classList.add('editorjs-holder')
    holder.querySelector('.ce-block')!.classList.add('ce-block--selected')
    const error = new DOMException('Clipboard denied', 'NotAllowedError')
    write.mockRejectedValueOnce(error)
    const logged = vi.spyOn(console, 'error').mockImplementation(() => {})
    const reported = vi.fn()
    holder.addEventListener('pw:editor-error', reported)
    cm.handleCopyShortcut(ctrlC(holder))
    await vi.waitFor(() => expect(reported).toHaveBeenCalledOnce())
    expect(reported.mock.calls[0]![0].detail).toEqual({ operation: 'copy', error })
    logged.mockRestore()
  })

  it.each([
    ['<b>One two three</b>', '<b>two</b>', '**two**'],
    ['<b><i>One two three</i></b>', '<b><i>two</i></b>', '**_two_**'],
    ['<a href="https://example.org">One two three</a>', '<a href="https://example.org">two</a>', '[two](https://example.org)'],
  ])('copies formatting enclosing the entire selection: %s', (html, expectedHtml, markdown) => {
    const { cm, holder } = copyingManager([paragraph('p', html)])
    const field = holder.querySelector('.ce-paragraph')!
    const text = document.createTreeWalker(field, NodeFilter.SHOW_TEXT).nextNode()!
    const range = document.createRange()
    range.setStart(text, 4)
    range.setEnd(text, 7)
    window.getSelection()!.addRange(range)
    const event = { ...ctrlC(field), clipboardData: { setData: vi.fn() } }

    cm.handleCopy(event)

    expect(event.clipboardData.setData).toHaveBeenCalledWith('text/html', expectedHtml)
    expect(event.clipboardData.setData).toHaveBeenCalledWith('text/plain', markdown)
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
    cm.editor.blocks = { getById: () => ({ name: 'paragraph' }) }
    cm.editor.sanitizer = { clean: (html: string) => html }
    cm._editorjsTools = [{ name: 'paragraph', baseSanitizeConfig: { b: true, i: true, a: { href: true } } }]
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

  it.each(['input', 'textarea'])('leaves paste in a native %s alone when Firefox retains the editor range', (tag) => {
    caretInBlock()
    const target = document.body.appendChild(document.createElement(tag))
    const event = { ...pasteEvent('**Pasted text**', '<p>Pasted text</p>'), target }
    const insert = vi.spyOn(cm, 'insertMarkdownAsBlocks').mockImplementation(() => {})

    cm.handlePaste(event)

    expect(event.preventDefault).not.toHaveBeenCalled()
    expect(insert).not.toHaveBeenCalled()
  })

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

  it.each([
    ['**bold**', '<b>bold</b>'],
    ['_italic_', '<i>italic</i>'],
    ['[link](https://example.org)', '<a href="https://example.org">link</a>'],
    ['**Copy me**', '<html>\r\n<body>\r\n<!--StartFragment--><b>Copy me</b><!--EndFragment-->\r\n</body>\r\n</html>'],
  ])('inserts only the inline fragment at the caret: %s', (plain, html) => {
    caretInBlock()
    const insert = vi.spyOn(cm, 'insertMarkdownAsBlocks')
    const command = vi.fn(() => true)
    document.execCommand = command
    const event = pasteEvent(plain, html)

    cm.handlePaste(event)

    expect(event.preventDefault).toHaveBeenCalledOnce()
    expect(event.stopPropagation).toHaveBeenCalledOnce()
    expect(command).toHaveBeenCalledWith('insertHTML', false, plain === '**Copy me**' ? '<b>Copy me</b>' : html)
    expect(insert).not.toHaveBeenCalled()
  })

  it.each(['', '<p><b>bold</b></p>'])('still imports a copied Markdown block with HTML %s', (html) => {
    caretInBlock()
    const insert = vi.spyOn(cm, 'insertMarkdownAsBlocks').mockImplementation(() => {})
    const event = pasteEvent('**bold**', html)

    cm.handlePaste(event)

    expect(event.preventDefault).toHaveBeenCalled()
    expect(insert).toHaveBeenCalledWith('**bold**')
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

const wordBullet = (level: number, text: string, glyph = '·') =>
  `<p class="MsoListParagraph" style="mso-list:l0 level${level} lfo1"><span style="font-family:Symbol;mso-list:Ignore">${glyph}<span>&nbsp;&nbsp;&nbsp;</span></span>${text}</p>\r\n`

describe('ClipboardManager – Word lists', () => {
  it('keeps two Word lists apart when a paragraph separates them', () => {
    const html = wordBullet(1, 'One') + '<p class="MsoNormal">Between</p>' + wordBullet(1, 'Two')

    expect(newManager().convertHtmlToMarkdown(html).trim()).toBe('- One\n\nBetween\n\n- Two')
  })

  it('rebuilds Word list paragraphs as a nested list without their glyphs', () => {
    const html = `<!--StartFragment-->${wordBullet(1, 'Option <b>A</b>')}${wordBullet(2, 'Detail', 'o')}${wordBullet(1, 'Option B')}<!--EndFragment-->`

    expect(newManager().convertHtmlToMarkdown(html).trim()).toBe('- Option **A**\n  - Detail\n- Option B')
  })

  it('numbers a Word list whose glyphs are numbers, even when its plain text looks like markdown', () => {
    const html = wordBullet(1, 'One', '1.') + wordBullet(2, 'Sub', 'a)') + wordBullet(1, 'Two', '2.')

    expect(newManager().clipboardMarkdown('1.\tOne\na)\tSub\n2.\tTwo', html).trim()).toBe('1. One\n   1. Sub\n2. Two')
  })
})

describe('ClipboardManager – pasting into a list item', () => {
  let cm: AnyCm
  let call: ReturnType<typeof vi.fn>
  beforeEach(() => {
    call = vi.fn()
    cm = new ClipboardManager({ editor: {
      blocks: { getById: (id: string) => (id === 'list-block' ? { name: 'list', call } : null) },
      sanitizer: { clean: (html: string) => html.replace(/<\/?u>/g, '') },
      tools: { getBlockTools: () => [
        { name: 'header', constructable: Header },
        { name: 'list', constructable: List, sanitizeConfig: { items: { b: true, br: true } } },
        { name: 'paragraph', constructable: Paragraph },
        { name: 'image', constructable: Image },
      ] },
    } } as any) as unknown as AnyCm
    document.body.innerHTML = '<div class="ce-block" data-id="list-block"><div class="ce-block__content"><div class="cdx-list">' +
      '<div class="cdx-list__item"><div class="cdx-list__item-content" contenteditable="true"></div></div></div></div></div>'
    const range = document.createRange()
    range.setStart(document.querySelector('.cdx-list__item-content')!, 0)
    window.getSelection()!.removeAllRanges()
    window.getSelection()!.addRange(range)
  })

  function paste(data: Record<string, string>): any {
    const event = {
      clipboardData: { getData: (type: string) => data[type] ?? '' },
      preventDefault: vi.fn(),
      stopPropagation: vi.fn(),
    }
    cm.handlePaste(event)
    return event
  }

  it.each([
    ['plain lines', { 'text/plain': 'Line one\r\n\r\nLine <two>\nLine three' }, [['Line one', 0], ['Line &lt;two&gt;', 0], ['Line three', 0]]],
    ['HTML paragraphs', { 'text/plain': 'A\n\nB', 'text/html': '<p>A</p><p>B</p>' }, [['A', 0], ['B', 0]]],
    ['an HTML list', { 'text/plain': 'A\nB', 'text/html': '<ul><li><u>A</u><ul><li><b>B</b></li></ul></li></ul>' }, [['A', 0], ['<b>B</b>', 1]]],
    ['Word bullets', { 'text/plain': '·   Option A\n·   Option B', 'text/html': wordBullet(1, 'Option A') + wordBullet(2, 'Option B') }, [['Option A', 0], ['Option B', 1]]],
    ['markdown copied from this editor', { 'text/plain': '- First\n  - Nested\n\n## Next day' }, [['First', 0], ['Nested', 1], ['Next day', 0]]],
    ['Editor.js blocks', { 'text/plain': 'First', 'application/x-editor-js': JSON.stringify([
      { tool: 'list', data: { style: 'unordered', items: [{ content: 'First', meta: {}, items: [{ content: 'Nested', meta: {}, items: [] }] }] } },
      { tool: 'header', data: { text: 'Next day', level: 2 } },
    ]) }, [['First', 0], ['Nested', 1], ['Next day', 0]]],
  ])('hands %s to the list as items', (_source, data, expected) => {
    const event = paste(data)

    expect(event.preventDefault).toHaveBeenCalledOnce()
    expect(call).toHaveBeenCalledWith('pasteItems', {
      items: expected.map(([content, depth]) => ({ content, depth })),
    })
  })

  it('leaves a single plain line to the browser', () => {
    const event = paste({ 'text/plain': 'Line one' })

    expect(event.preventDefault).not.toHaveBeenCalled()
    expect(call).not.toHaveBeenCalled()
  })

  it('inserts a single markdown line at the caret rather than as a block after the list', () => {
    const command = vi.fn(() => true)
    document.execCommand = command
    const insert = vi.spyOn(cm, 'insertMarkdownAsBlocks')

    paste({ 'text/plain': '**Length** : 12km' })

    expect(command).toHaveBeenCalledWith('insertHTML', false, '<b>Length</b> : 12km')
    expect(call).not.toHaveBeenCalled()
    expect(insert).not.toHaveBeenCalled()
  })

  it('leaves content no item can hold to the block import', () => {
    const insert = vi.spyOn(cm, 'insertMarkdownAsBlocks').mockImplementation(() => {})

    paste({ 'text/plain': '- First\n\n![Photo](photo.jpg)' })

    expect(call).not.toHaveBeenCalled()
    expect(insert).toHaveBeenCalledOnce()
  })

  it('reads legacy string and checklist items of copied Editor.js blocks', () => {
    paste({ 'text/plain': 'x', 'application/x-editor-js': JSON.stringify([
      { tool: 'list', data: { style: 'unordered', items: ['Legacy', { text: 'Check', items: ['Sub'] }] } },
    ]) })

    expect(call).toHaveBeenCalledWith('pasteItems', { items: [
      { content: 'Legacy', depth: 0 }, { content: 'Check', depth: 0 }, { content: 'Sub', depth: 1 },
    ] })
  })

  it('splits a pasted paragraph on its soft line breaks, hard breaks kept inside the item', () => {
    paste({ 'text/plain': 'x', 'application/x-editor-js': JSON.stringify([
      { tool: 'paragraph', data: { text: 'One<br>still one\nTwo' } },
    ]) })

    expect(call).toHaveBeenCalledWith('pasteItems', { items: [
      { content: 'One<br>still one', depth: 0 }, { content: 'Two', depth: 0 },
    ] })
  })

  it('leaves unreadable Editor.js data to the block import', () => {
    vi.spyOn(cm, 'insertMarkdownAsBlocks').mockImplementation(() => {})

    expect(() => paste({ 'text/plain': 'A\nB', 'application/x-editor-js': '{not json' })).not.toThrow()

    expect(call).not.toHaveBeenCalled()
  })

  it('does not hand the paste to a field of a block whose tool cannot take items', () => {
    document.querySelector('.ce-block')!.setAttribute('data-id', 'other')

    const event = paste({ 'text/plain': 'Line one\nLine two' })

    expect(call).not.toHaveBeenCalled()
    expect(event.preventDefault).not.toHaveBeenCalled()
  })
})

describe('ClipboardManager – plain text of a block copy left to Editor.js', () => {
  beforeEach(() => {
    newManager()
    document.body.innerHTML =
      '<div class="ce-block ce-block--selected"><div class="ce-block__content"><div class="cdx-list">' +
      '<div class="cdx-list__item"><div class="cdx-list__item-content" contenteditable="true">First<br>line two</div>' +
      '<div class="cdx-list__item-children"><div class="cdx-list__item"><div class="cdx-list__item-content" contenteditable="true">Nested</div></div></div></div>' +
      '</div></div></div>' +
      '<div class="ce-block ce-block--selected"><div class="ce-block__content"><div class="ce-header-level-wrapper"><select><option>H2</option><option>H3</option></select></div>' +
      '<h2 class="ce-header" contenteditable="true">Next day</h2></div></div>' +
      '<div class="ce-block"><div class="ce-block__content"><div contenteditable="true">After</div></div></div>'
  })

  function dispatch(type: string, editorJs: (event: Event) => void): ReturnType<typeof vi.fn> {
    const setData = vi.fn()
    const event = new Event(type, { bubbles: true, cancelable: true })
    Object.defineProperty(event, 'clipboardData', { value: { setData } })
    document.addEventListener(type, editorJs, { once: true })
    document.querySelector('h2')!.dispatchEvent(event)
    return setData
  }

  it.each(['copy', 'cut'])('writes one line per field on %s, after Editor.js and without the heading level picker', (type) => {
    const setData = dispatch(type, (event) => {
      event.preventDefault()
      ;(event as ClipboardEvent).clipboardData!.setData('text/plain', 'Firstline twoNested\n\nH2H3Next day')
      // A cut removes the blocks before the event is done.
      if (type === 'cut') document.querySelectorAll('.ce-block--selected').forEach((block) => block.remove())
    })

    expect(setData).toHaveBeenLastCalledWith('text/plain', 'First\nline two\nNested\n\nNext day')
  })

  it('leaves a copy nobody handled to the browser', () => {
    const setData = dispatch('copy', () => {})

    expect(setData).not.toHaveBeenCalled()
  })
})
