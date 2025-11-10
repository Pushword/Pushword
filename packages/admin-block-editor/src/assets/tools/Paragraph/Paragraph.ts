import ParagraphTool from '@editorjs/paragraph'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { BlockToolData, API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'

export interface ParagraphData extends BlockToolData {
  text?: string
}

export default class Paragraph extends ParagraphTool {
  static async exportToMarkdown(
    data: ParagraphData,
    tunes: BlockTuneData,
  ): Promise<string> {
    if (!data || !data.text) {
      return ''
    }

    let markdown = data.text
      .replace(/(&nbsp;| |\u00A0)+ */g, ' ')
      .split('<br>')
      .join('  \n')
    markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown)
    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown)
    return MarkdownUtils.addAttributes(formattedMarkdown, tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    let tunes: BlockTuneData = result.tunes
    let markdownWithoutTunes = result.markdown

    markdownWithoutTunes = markdownWithoutTunes
      .split('\n')
      .join('<br>')
      .replace(/<br>$/, '')

    markdownWithoutTunes = MarkdownUtils.convertInlineMarkdownToHtml(markdownWithoutTunes)

    const block = editor.blocks.insert('paragraph')
    editor.blocks.update(
      block.id,
      {
        text: markdownWithoutTunes,
      },
      tunes,
    )
  }

  // TODO : à revoir pour voir qui est le défault, raw ou paragraph
  static isItMarkdownExported(markdown: string): boolean {
    const trimmed = markdown.trim()

    // starts with '<' = probably html
    // starts with '{' = probably twig function
    // starts with '-->' = probably a hack to comment close a previously opened comment
    // starts with '#}' = same
    const isProbablyNotMarkdown = /^(<|{|-->|#})/.test(trimmed)

    // Return true only if it doesn't start with those patterns
    return !isProbablyNotMarkdown
  }
}
