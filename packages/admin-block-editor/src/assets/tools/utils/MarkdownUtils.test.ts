import { describe, it, expect } from 'vitest'
import { MarkdownUtils } from './MarkdownUtils'

describe('MarkdownUtils.extractSnippetCall', () => {
  it('extracts the name from a single-quoted call', () => {
    expect(MarkdownUtils.extractSnippetCall("{{ snippet('hero') }}")).toEqual({
      name: 'hero',
      params: {},
    })
  })

  it('extracts the name from a double-quoted call', () => {
    expect(MarkdownUtils.extractSnippetCall('{{ snippet("cta") }}')).toEqual({
      name: 'cta',
      params: {},
    })
  })

  it('tolerates extra whitespace around the name argument', () => {
    expect(MarkdownUtils.extractSnippetCall("{{ snippet(  'box'  ) }}")).toEqual({
      name: 'box',
      params: {},
    })
  })

  it('parses a params object after the name', () => {
    expect(
      MarkdownUtils.extractSnippetCall("{{ snippet('box', { color: 'red', size: 3 }) }}"),
    ).toEqual({ name: 'box', params: { color: 'red', size: 3 } })
  })

  it('returns null when there is no snippet call', () => {
    expect(MarkdownUtils.extractSnippetCall('just some text')).toBeNull()
  })

  it('returns null when the first argument is not a quoted string', () => {
    expect(MarkdownUtils.extractSnippetCall('{{ snippet(foo) }}')).toBeNull()
  })

  it('stops at the end of a truncated call without a closing paren', () => {
    expect(MarkdownUtils.extractSnippetCall("{{ snippet('x'")).toEqual({
      name: 'x',
      params: {},
    })
  })
})

describe('MarkdownUtils.chunkMarkdown', () => {
  /** The rule chunkMarkdown must reproduce byte-for-byte: the parser's historical split. */
  function legacyChunks(markdown: string): string[] {
    if (markdown.trim() === '') return []
    return markdown.replace(/\n\s*\n+/g, '\n\n').split('\n\n')
  }

  it.each([
    ['two blocks', 'a\n\nb'],
    ['single block', 'a'],
    ['multi-line block', 'line1\nline2\n\nb'],
    ['extra blank lines', 'a\n\n\n\nb'],
    ['whitespace-only separator lines', 'a\n  \t\nb'],
    ['leading blank lines', '\n\na'],
    ['trailing blank lines', 'a\n\n'],
    ['empty string', ''],
    ['whitespace-only string', '  \n \n '],
  ])('matches the parser split for %s', (_label, markdown) => {
    expect(MarkdownUtils.chunkMarkdown(markdown).map((chunk) => chunk.text)).toEqual(
      legacyChunks(markdown),
    )
  })

  it('maps each chunk to its source lines', () => {
    const chunks = MarkdownUtils.chunkMarkdown('# T\n\npara line1\npara line2\n\nlast')

    expect(chunks).toEqual([
      { text: '# T', startLine: 0, endLine: 0 },
      { text: 'para line1\npara line2', startLine: 2, endLine: 3 },
      { text: 'last', startLine: 5, endLine: 5 },
    ])
  })

  it('keeps line positions across collapsed blank-line runs', () => {
    const chunks = MarkdownUtils.chunkMarkdown('a\n\n\n\nb')

    expect(chunks).toEqual([
      { text: 'a', startLine: 0, endLine: 0 },
      { text: 'b', startLine: 4, endLine: 4 },
    ])
  })

  it('anchors an empty leading chunk at line zero', () => {
    expect(MarkdownUtils.chunkMarkdown('\n\na')).toEqual([
      { text: '', startLine: 0, endLine: 0 },
      { text: 'a', startLine: 2, endLine: 2 },
    ])
  })
})
