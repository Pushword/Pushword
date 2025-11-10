import DelimiterTool from '@editorjs/delimiter'
import { BlockToolData, API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'

export default class Delimiter extends DelimiterTool {
  /**
   * Export block data to Markdown
   * @param {BlockToolData} data - Block data
   * @param {BlockTuneData} tunes - Block tunes
   * @returns {string} Markdown representation
   */
  // @ts-ignore
  static exportToMarkdown(data: BlockToolData, tunes?: BlockTuneData): string {
    return '---'
  }

  static importFromMarkdown(editor: API): void {
    editor.blocks.insert('delimiter')
  }

  static isItMarkdownExported(markdown: string): boolean {
    return markdown.trim().match(/^-{3,}$/) !== null && markdown.split('\n').length === 1
  }
}
