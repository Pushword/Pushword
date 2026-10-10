import QuoteTool from '@editorjs/quote'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { BlockToolData, API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'

export interface QuoteData extends BlockToolData {
  text?: string
  caption?: string
}

export default class Quote extends QuoteTool {
  static exportToMarkdown(data: QuoteData, tunes?: BlockTuneData): string {
    if (!data || !data.text) {
      return ''
    }

    const lines = MarkdownUtils.htmlLines(data.text).map((line) => line.trim())

    // Handle caption if present
    if (data.caption) {
      lines.push(`— <cite>${data.caption}</cite>`)
    }

    return MarkdownUtils.addAttributes(MarkdownUtils.toBlockquote(lines), tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    const tunes: BlockTuneData = result.tunes
    const markdownWithoutTunes = result.markdown

    const lines = markdownWithoutTunes.split('\n')
    let caption = ''
    const quoteLines: string[] = []
    let inQuote = true

    for (const line of lines) {
      if (line.trim().match(/^>\s*(—|-)/) || !inQuote) {
        inQuote = false
        caption += line
          .trim()
          .replace(/^>\s*(—|-)\s*(<cite>)?/, '')
          .replace(/<\/cite>\s*$/, '')
        continue
      }
      if (line.trim().startsWith('>')) {
        quoteLines.push(line.trim())
      }
    }

    caption = caption.trim()
    const quoteText = MarkdownUtils.fromBlockquote(quoteLines).join('<br>').trim()

    const block = editor.blocks.insert('quote')
    editor.blocks.update(
      block.id,
      {
        text: quoteText,
        caption: caption,
      },
      tunes,
    )
  }

  static isItMarkdownExported(markdown: string): boolean {
    return markdown.startsWith('> ')
  }
}
