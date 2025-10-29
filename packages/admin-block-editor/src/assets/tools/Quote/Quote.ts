import QuoteTool from '@editorjs/quote'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { BlockToolData, API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'

export interface QuoteData extends BlockToolData {
  text?: string
  caption?: string
}

export default class Quote extends QuoteTool {
  static exportToMarkdown(data: QuoteData, tunes: BlockTuneData): string {
    if (!data || !data.text) {
      return ''
    }

    let markdown = ''

    const lines = data.text.split(/<br\s*\/?>/gi)
    for (const line of lines) {
      markdown += `> ${line.trim()}` + '\n'
    }

    // Handle caption if present
    if (data.caption) {
      markdown += `> — <cite>${data.caption}</cite>`
    }

    return MarkdownUtils.addAttributes(markdown, tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    let tunes: BlockTuneData = result.tunes
    let markdownWithoutTunes = result.markdown

    const lines = markdownWithoutTunes.split('\n')
    let i = 0
    let caption = ''
    let quoteText = ''
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
        quoteText += line.trim().replace(/^>\s?/, '') + '<br>'
      }
      i++
    }

    caption = caption.trim()
    quoteText = quoteText.replace(/<br>$/, '').trim()

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
