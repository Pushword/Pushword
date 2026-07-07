import { describe, it, expect } from 'vitest'
import TableBlock from './plugin'

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

describe('TableBlock.importFromMarkdown (HTML table)', () => {
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

  it('imports a headerless HTML table with an empty header row', () => {
    const { editor, updates } = fakeEditor()

    TableBlock.importFromMarkdown(
      editor,
      '<table><tbody><tr><td><strong>En bref</strong></td><td>desc</td></tr></tbody></table>',
    )

    expect(updates).toHaveLength(1)
    expect(updates[0].data.withHeadings).toBe(true)
    expect(updates[0].data.content[0]).toEqual(['', ''])
    expect(updates[0].data.content[1]).toEqual(['<strong>En bref</strong>', 'desc'])
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

    expect(updates[0].data.content[1]).toEqual(['a', 'b'])
    expect(updates[0].tunes.anchor).toBe('specs')
  })
})
