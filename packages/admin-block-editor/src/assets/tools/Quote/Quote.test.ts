import { describe, it, expect } from 'vitest'
import { API } from '@editorjs/editorjs'
import Quote, { QuoteData } from './Quote'
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

function importQuote(markdown: string): QuoteData {
  let captured: QuoteData | null = null
  const editor = {
    blocks: {
      insert: () => ({ id: 'block-id' }),
      update: (_id: string, data: QuoteData) => {
        captured = data
      },
    },
  } as unknown as API

  Quote.importFromMarkdown(editor, markdown)

  if (captured === null) {
    throw new Error('quote block was never updated')
  }

  return captured
}

/**
 * Quote keeps its text as it comes, markdown or HTML: unlike Notice it does not
 * run it through convertInlineHtmlToMarkdown(), which decodes entities and would
 * turn a quoted `&lt;div&gt;` into a real tag.
 */
describe('Quote markdown round trip', () => {
  it.each([
    ['inline markdown', '> **bold**, _italic_, [a link](/page) and `code`'],
    [
      'inline HTML',
      '> <b>bold</b>, <i>italic</i>, <a href="/page">a link</a> and <code>code</code>',
    ],
    ['an escaped tag', '> Use the &lt;div&gt; element'],
    ['several paragraphs and a caption', '> One.\n>\n> Two.\n> — <cite>Author</cite>'],
    ['the tunes of an attribute line', '{#citation}\n> Quoted.'],
  ])('keeps %s', (_case, markdown) => {
    const { anchor } = MarkdownUtils.parseTunesFromMarkdown(markdown).tunes

    expect(Quote.exportToMarkdown(importQuote(markdown), { anchor })).toBe(markdown)
  })
})
