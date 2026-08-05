import { describe, it, expect } from 'vitest'
import Quote from './Quote'
import Notice from '../Notice/Notice'
import Paragraph from '../Paragraph/Paragraph'
import Raw from '../Raw/Raw'
import { chunkTool } from '../../EditorJsParseMarkdown'
import { GroupNesting } from '../Group/GroupNesting'
import { MarkdownUtils } from '../utils/MarkdownUtils'

/**
 * Quote claims any `> ` chunk, so a notice marker would land in it: the tools
 * are registered with Notice first for that reason, and the importer takes the
 * first claim. Guards that order, which nothing else would catch — a notice
 * imported as a quote still round-trips, it just loses its editing UI.
 */
describe('a notice chunk goes to Notice, not Quote', () => {
  const tools = [
    { name: 'notice', constructable: Notice },
    { name: 'quote', constructable: Quote },
    { name: 'paragraph', constructable: Paragraph },
    { name: 'raw', constructable: Raw },
  ] as any[]

  const classify = (markdown: string): string[] => {
    const nesting = new GroupNesting()
    return MarkdownUtils.chunkMarkdown(markdown).map(
      (chunk) => chunkTool(tools, chunk.text, nesting)?.name ?? 'none',
    )
  }

  it('routes a marked blockquote to notice', () => {
    expect(classify('> [!warning] Version\n>\n> Last updated.')).toEqual(['notice'])
  })

  it('routes an ordinary blockquote to quote', () => {
    expect(classify('> Just a quote\n> — <cite>Author</cite>')).toEqual(['quote'])
  })

  it('routes an escaped marker to quote', () => {
    expect(classify('> \\[!NOTE] this stays a quotation')).toEqual(['quote'])
  })

  it('keeps both in one document', () => {
    expect(classify('> [!tip] Tip\n> body\n\n> A quotation')).toEqual(['notice', 'quote'])
  })
})
