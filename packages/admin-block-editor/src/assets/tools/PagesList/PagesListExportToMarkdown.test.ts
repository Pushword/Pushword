import { describe, it, expect } from 'vitest'
import { exportPagesListToMarkdown } from './PagesListExportToMarkdown'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { PagesListData } from './PagesList'

const data = (overrides: Partial<PagesListData> = {}): PagesListData => ({
  kw: 'type:blog',
  display: 'list',
  order: 'publishedAt ↓',
  max: '9',
  maxPages: '0',
  ...overrides,
})

describe('exportPagesListToMarkdown', () => {
  it('writes the display verbatim, so a new view needs no change here', () => {
    expect(exportPagesListToMarkdown(data({ display: 'horizontalScroll' }))).toContain(
      "'horizontalScroll'",
    )
  })

  it('falls back to list when the display is empty', () => {
    expect(exportPagesListToMarkdown(data({ display: '' }))).toContain("'list'")
  })

  it('renders nothing without a keyword', () => {
    expect(exportPagesListToMarkdown(data({ kw: '' }))).toBe('')
  })
})

/**
 * The round trip is what a saved block depends on: importFromMarkdown reads the
 * display back by position (properties[3]), so a shifted argument would silently
 * turn a scroller into a plain list on the next edit.
 */
describe('pages_list markdown round trip', () => {
  it.each(['list', 'card', 'horizontalScroll'])(
    'reads %s back from the position importFromMarkdown uses',
    (display) => {
      const markdown = exportPagesListToMarkdown(data({ display }))
      const properties = MarkdownUtils.extractTwigFunctionProperties('pages_list', markdown)

      expect(properties).not.toBeNull()
      expect(properties?.[3]).toBe(display)
    },
  )

  it('keeps the display in place when maxPages and tunes are also exported', () => {
    const markdown = exportPagesListToMarkdown(data({
      display: 'horizontalScroll',
      maxPages: '3',
    }), { class: 'bg-pink-50', anchor: 'listing' })
    const properties = MarkdownUtils.extractTwigFunctionProperties('pages_list', markdown)

    expect(properties?.[3]).toBe('horizontalScroll')
    expect(properties?.[4]).toBe('3')
    expect(properties?.[5]).toBe('bg-pink-50')
    expect(properties?.[6]).toBe('listing')
  })
})

/**
 * What a hand-written call has to look like to stay editable. The reader accepts
 * quoted positional arguments only, so a named argument or a bare number makes the
 * whole call unreadable and the block imports as raw markdown — it still renders,
 * but the format select never sees it. Documented in /pages-list.
 */
describe('pages_list markdown the editor can read back', () => {
  const read = (markdown: string) =>
    MarkdownUtils.extractTwigFunctionProperties('pages_list', markdown)

  it('reads a quoted positional call, wrapperClass included', () => {
    const properties = read(
      "{{ pages_list('type:blog', '9', 'publishedAt ↓', 'horizontalScroll', '0', 'bleed') }}",
    )

    expect(properties?.[3]).toBe('horizontalScroll')
    expect(properties?.[5]).toBe('bleed')
  })

  it.each([
    ["a named argument", "{{ pages_list('type:blog', '9', 'publishedAt ↓', 'horizontalScroll', wrapperClass: 'bleed') }}"],
    ["an unquoted number", "{{ pages_list('type:blog', 9, 'publishedAt ↓', 'horizontalScroll') }}"],
  ])('gives up on %s', (_label, markdown) => {
    expect(read(markdown)).toBeNull()
  })
})
