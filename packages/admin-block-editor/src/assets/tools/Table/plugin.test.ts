import { describe, it, expect, vi, beforeEach } from 'vitest'
import TableBlock from './plugin'
import { MarkdownUtils } from '../utils/MarkdownUtils'

describe('TableBlock.splitPipeRow', () => {
  it('drops only the outer pipe artifacts', () => {
    expect(TableBlock.splitPipeRow('| a | b |')).toEqual(['a', 'b'])
  })

  it('keeps an all-empty (header) row', () => {
    expect(TableBlock.splitPipeRow('|  |  |')).toEqual(['', ''])
  })

  it('keeps interior empty cells', () => {
    expect(TableBlock.splitPipeRow('| a |  | c |')).toEqual(['a', '', 'c'])
  })

  it('handles a row without outer pipes', () => {
    expect(TableBlock.splitPipeRow('A | B')).toEqual(['A', 'B'])
  })
})

describe('TableBlock.isItMarkdownExported', () => {
  it('claims a GFM pipe table', () => {
    expect(TableBlock.isItMarkdownExported('| a | b |')).toBe(true)
  })

  it('claims a simple HTML table', () => {
    expect(
      TableBlock.isItMarkdownExported('<table><tr><td>a</td><td>b</td></tr></table>'),
    ).toBe(true)
  })

  it('rejects a complex HTML table (colspan) so it falls back to Raw', () => {
    expect(
      TableBlock.isItMarkdownExported(
        '<table><tr><td colspan="2">a</td></tr><tr><td>b</td><td>c</td></tr></table>',
      ),
    ).toBe(false)
  })

  it('rejects plain text', () => {
    expect(TableBlock.isItMarkdownExported('just a paragraph')).toBe(false)
  })
})

function fakeEditor(): { editor: any; updates: any[] } {
  const updates: any[] = []
  const editor = {
    blocks: {
      insert: () => ({ id: 'b1' }),
      update: (id: string, data: any, tunes: any) => updates.push({ id, data, tunes }),
    },
  }

  return { editor, updates }
}

describe('TableBlock inline Markdown in cells', () => {
  beforeEach(() => {
    // Prettier is fetched from the bundle's public path — out of reach here, and
    // its pipe alignment would only blur what these tests assert.
    vi.spyOn(MarkdownUtils, 'formatMarkdownWithPrettier').mockImplementation(
      async (markdown: string) => markdown.trim(),
    )
  })

  it('adds sticky heading class inside an existing attribute line', async () => {
    const markdown = await TableBlock.exportToMarkdown(
      { content: [['Heading']], withHeadings: true, stickyHeadings: true },
      { class: 'custom' } as any,
    )

    expect(markdown.startsWith('{.custom .table-sticky-header}\n')).toBe(true)
  })

  it('imports inline Markdown as the HTML a cell renders', () => {
    const { editor, updates } = fakeEditor()

    TableBlock.importFromMarkdown(
      editor,
      '| **PHP Version** | 8.4+ |\n| --- | --- |\n| _italic_ | `code` |',
    )

    expect(updates[0].data.content[0]).toEqual(['<b>PHP Version</b>', '8.4+'])
    expect(updates[0].data.content[1]).toEqual([
      '<i>italic</i>',
      '<code class="inline-code">code</code>',
    ])
  })

  it('keeps a cell line break inside its pipe row', async () => {
    const markdown = await TableBlock.exportToMarkdown({
      content: [['one<br>two', 'b']],
      withHeadings: true,
    })

    expect(markdown.split('\n')[0]).toBe('| one<br>two | b |')
  })

  it('leaves a `->` colspan marker alone', async () => {
    const markdown = await TableBlock.exportToMarkdown({
      content: [['spanned', '-&gt;']],
      withHeadings: true,
    })

    expect(markdown.split('\n')[0]).toBe('| spanned | -> |')
  })

  it('gives back every marker it was imported with', async () => {
    const { editor, updates } = fakeEditor()
    const source = '| **b** | _i_ | `c` | ~~s~~ | [t](/u) |\n| --- | --- | --- | --- | --- |'

    TableBlock.importFromMarkdown(editor, source)
    const markdown = await TableBlock.exportToMarkdown(updates[0].data)

    expect(markdown).toBe(source)
  })

  it('allows every tag the inline converter emits through the sanitizer', () => {
    // editor.js cleans each cell string with this map on save, so a tag the
    // import can produce but the map omits loses its Markdown marker on save.
    const converted = MarkdownUtils.convertInlineMarkdownToHtml(
      '**b** _i_ `c` ~~s~~ [t](/u)  \nbr',
    )
    const tags = [...converted.matchAll(/<([a-z]+)[\s>]/g)].map((match) => match[1]!)

    // Pinned so a marker added to the converter fails here rather than silently
    // widening what the map has to allow.
    expect(tags).toEqual(['b', 'i', 'code', 's', 'a', 'br'])
    tags.forEach((tag) => expect(TableBlock.sanitize).toHaveProperty(tag, true))
  })
})

describe('TableBlock without headings', () => {
  beforeEach(() => {
    vi.spyOn(MarkdownUtils, 'formatMarkdownWithPrettier').mockImplementation(
      async (markdown: string) => markdown.trim(),
    )
  })

  it('exports under an empty header, since CommonMark reads no table without one', async () => {
    const markdown = await TableBlock.exportToMarkdown({
      content: [['a', 'b'], ['c', 'd']],
      withHeadings: false,
    })

    expect(markdown).toBe('|  |  |\n| --- | --- |\n| a | b |\n| c | d |')
  })

  it('writes its column alignments on the delimiter row', async () => {
    const markdown = await TableBlock.exportToMarkdown({
      content: [['a', 'b']],
      columnAlignments: ['center', 'right'],
    })

    expect(markdown.split('\n')[1]).toBe('| :--: | ---: |')
  })

  it('reads the empty header back as no headings, alignments kept', () => {
    const { editor, updates } = fakeEditor()

    TableBlock.importFromMarkdown(editor, '|  |  |\n| :--: | --- |\n| a | b |')

    expect(updates[0].data.withHeadings).toBe(false)
    expect(updates[0].data.content).toEqual([['a', 'b']])
    expect(updates[0].data.columnAlignments).toEqual(['center', ''])
  })

  it('repairs a table saved without a delimiter row by the export before this one', async () => {
    const { editor, updates } = fakeEditor()

    TableBlock.importFromMarkdown(editor, '| a | b |\n| c | d |')

    expect(updates[0].data.withHeadings).toBe(false)
    expect(await TableBlock.exportToMarkdown(updates[0].data)).toBe(
      '|  |  |\n| --- | --- |\n| a | b |\n| c | d |',
    )
  })

  it('keeps a header with a single filled cell as headings', () => {
    const { editor, updates } = fakeEditor()

    TableBlock.importFromMarkdown(editor, '|  | B |\n| --- | --- |\n| a | b |')

    expect(updates[0].data.withHeadings).toBe(true)
    expect(updates[0].data.content).toEqual([['', 'B'], ['a', 'b']])
  })

  it('exports a headerless HTML table under the empty header', async () => {
    const { editor, updates } = fakeEditor()

    TableBlock.importFromMarkdown(editor, '<table><tr><td>a</td><td>b</td></tr></table>')

    expect(await TableBlock.exportToMarkdown(updates[0].data)).toBe('|  |  |\n| --- | --- |\n| a | b |')
  })

  it('keeps an empty header that has no row under it', () => {
    const { editor, updates } = fakeEditor()

    TableBlock.importFromMarkdown(editor, '|  |  |\n| --- | --- |')

    expect(updates[0].data.withHeadings).toBe(true)
    expect(updates[0].data.content).toEqual([['', '']])
  })
})

describe('TableBlock.importFromMarkdown (HTML table)', () => {

  it('imports a headerless HTML table as a table without headings', () => {
    const { editor, updates } = fakeEditor()

    TableBlock.importFromMarkdown(
      editor,
      '<table><tbody><tr><td><strong>En bref</strong></td><td>desc</td></tr></tbody></table>',
    )

    expect(updates).toHaveLength(1)
    expect(updates[0].data.withHeadings).toBe(false)
    expect(updates[0].data.content).toEqual([['<strong>En bref</strong>', 'desc']])
  })

  it('imports a headed HTML table without injecting an empty row', () => {
    const { editor, updates } = fakeEditor()

    TableBlock.importFromMarkdown(
      editor,
      '<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>',
    )

    expect(updates[0].data.content).toEqual([
      ['A', 'B'],
      ['1', '2'],
    ])
  })

  it('passes a leading block-attribute line through as tunes', () => {
    const { editor, updates } = fakeEditor()

    TableBlock.importFromMarkdown(
      editor,
      '{#specs}\n<table><tr><td>a</td><td>b</td></tr></table>',
    )

    expect(updates[0].data.content).toEqual([['a', 'b']])
    expect(updates[0].tunes.anchor).toBe('specs')
  })
})
