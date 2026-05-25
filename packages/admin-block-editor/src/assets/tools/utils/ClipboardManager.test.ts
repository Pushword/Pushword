import { describe, it, expect, beforeEach, vi } from 'vitest'
import ClipboardManager from './ClipboardManager'

/**
 * Unit tests for the copy/paste pipeline. The handlers are private, so we reach
 * them through a cast; this keeps the tests close to the real call sites while
 * exercising the behaviours fixed in this package (multi-block table paste,
 * cell-level copy/paste, and the block-selection Ctrl+C shortcut).
 */

type AnyCm = ClipboardManager & Record<string, any>

function newManager(): AnyCm {
  // The constructor only needs an object to hang listeners off; the editor API
  // is lazily accessed and stubbed per-test where a method actually uses it.
  return new ClipboardManager({ editor: {} as any }) as AnyCm
}

/** Build a detached Editor.js-style table block. */
function buildTableBlock(opts: {
  rows: string[][]
  heading?: boolean
  alignments?: string[]
  sticky?: boolean
}): HTMLElement {
  const block = document.createElement('div')
  block.className = 'ce-block'
  if (opts.sticky) {
    const sticky = document.createElement('div')
    sticky.className = 'table-sticky-header'
    block.appendChild(sticky)
  }
  const wrap = opts.sticky ? block.querySelector('.table-sticky-header')! : block
  const table = document.createElement('div')
  table.className = 'tc-table' + (opts.heading ? ' tc-table--heading' : '')
  opts.rows.forEach((cells) => {
    const row = document.createElement('div')
    row.className = 'tc-row'
    cells.forEach((text, col) => {
      const cell = document.createElement('div')
      cell.className = 'tc-cell'
      cell.innerHTML = text
      if (opts.alignments?.[col]) cell.style.textAlign = opts.alignments[col]!
      row.appendChild(cell)
    })
    table.appendChild(row)
  })
  wrap.appendChild(table)
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
  })
})

describe('ClipboardManager – table extraction', () => {
  let cm: AnyCm
  beforeEach(() => {
    document.body.innerHTML = ''
    cm = newManager()
  })

  it('exports a heading table with per-column alignment markers', () => {
    const block = buildTableBlock({
      rows: [['A', 'B', 'C'], ['1', '2', '3']],
      heading: true,
      alignments: ['left', 'center', 'right'],
    })
    const result = cm.extractBlockContent(block)
    expect(result.markdown).toBe('| A | B | C |\n| :--- | :--: | ---: |\n| 1 | 2 | 3 |')
  })

  it('prefixes a sticky table with the block attribute', () => {
    const block = buildTableBlock({
      rows: [['A', 'B'], ['1', '2']],
      heading: true,
      sticky: true,
    })
    const result = cm.extractBlockContent(block)
    expect(result.markdown.startsWith('{.table-sticky-header}\n')).toBe(true)
  })

  it('escapes pipe characters inside cells', () => {
    const block = buildTableBlock({ rows: [['a | b', 'c'], ['d', 'e']], heading: true })
    const result = cm.extractBlockContent(block)
    expect(result.markdown.split('\n')[0]).toBe('| a \\| b | c |')
  })
})

describe('ClipboardManager – isSelectionWithinTableCell', () => {
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
    expect(cm.isSelectionWithinTableCell(sel)).toBe(true)
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
    expect(cm.isSelectionWithinTableCell(sel)).toBe(false)
  })
})

describe('ClipboardManager – handleCopyShortcut (block-selection Ctrl+C)', () => {
  let cm: AnyCm
  beforeEach(() => {
    document.body.innerHTML = ''
    cm = newManager()
    window.getSelection()?.removeAllRanges()
  })

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

  it('copies a selected table as markdown', () => {
    const holder = document.createElement('div')
    holder.id = 'editorjs_x'
    const block = buildTableBlock({ rows: [['A', 'B'], ['1', '2']], heading: true })
    block.classList.add('ce-block--selected')
    holder.appendChild(block)
    document.body.appendChild(holder)

    const write = vi.spyOn(cm, 'writeToClipboard').mockImplementation(() => {})
    const event = ctrlC(block)
    cm.handleCopyShortcut(event)

    expect(write).toHaveBeenCalledOnce()
    expect(write.mock.calls[0][0]).toBe('| A | B |\n| --- | --- |\n| 1 | 2 |')
    expect(event.preventDefault).toHaveBeenCalled()
  })

  it('bails when a text selection is present (inline copy handled by copy event)', () => {
    const holder = document.createElement('div')
    holder.id = 'editorjs_x'
    const block = buildTableBlock({ rows: [['A', 'B'], ['1', '2']], heading: true })
    block.classList.add('ce-block--selected')
    holder.appendChild(block)
    document.body.appendChild(holder)
    // a non-collapsed text selection
    const cell = block.querySelector('.tc-cell')!.firstChild!
    const range = document.createRange()
    range.setStart(cell, 0)
    range.setEnd(cell, 1)
    const sel = window.getSelection()!
    sel.removeAllRanges()
    sel.addRange(range)

    const write = vi.spyOn(cm, 'writeToClipboard').mockImplementation(() => {})
    const event = ctrlC(block)
    cm.handleCopyShortcut(event)

    expect(write).not.toHaveBeenCalled()
    expect(event.preventDefault).not.toHaveBeenCalled()
  })

  it('bails when no block is selected', () => {
    const holder = document.createElement('div')
    holder.id = 'editorjs_x'
    holder.appendChild(buildTableBlock({ rows: [['A', 'B']], heading: true }))
    document.body.appendChild(holder)

    const write = vi.spyOn(cm, 'writeToClipboard').mockImplementation(() => {})
    const event = ctrlC(holder)
    cm.handleCopyShortcut(event)

    expect(write).not.toHaveBeenCalled()
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
