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
