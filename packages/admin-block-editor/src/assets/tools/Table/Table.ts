import TableTool from '@editorjs/table'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { BlockToolData, API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'

export interface TableData extends BlockToolData {
  content?: string[][]
  withHeadings?: boolean
}

// TODO : À tester
export default class Table extends TableTool {
  /**
   * Export block data to Markdown
   * @param {TableData} data - Block data
   * @param {BlockTuneData} tunes - Block tunes
   * @returns {string} Markdown representation
   */
  static async exportToMarkdown(data: TableData, tunes?: BlockTuneData): Promise<string> {
    if (!data || !data.content) {
      return ''
    }

    const rows = data.content
    if (rows.length === 0) {
      return ''
    }

    let markdown = ''
    const withHeadings = data.withHeadings || false

    // Process each row
    rows.forEach((row: string[], rowIndex: number) => {
      const isHeaderRow = withHeadings && rowIndex === 0

      // Add row content
      markdown += '| ' + row.join(' | ') + ' |\n'

      // Add separator row for header
      if (isHeaderRow) {
        const separator = row.map(() => '---').join(' | ')
        markdown += '| ' + separator + ' |\n'
      }
    })

    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown)

    return MarkdownUtils.addAttributes(formattedMarkdown, tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): TableData {
    const lines = markdown.split('\n')
    let i = 0
    let tunes: BlockTuneData = {}
    const content: string[][] = []
    let withHeadings = false

    while (i < lines.length) {
      if (!lines[i]) {
        break
      }

      const line: string = lines[i] || ''

      if (i === 0 && MarkdownUtils.startWithAttribute(line)) {
        tunes = MarkdownUtils.parseAttributes(line)
        i++
        continue
      }

      if (line.includes('|')) {
        const cells = line
          .split('|')
          .map((cell) => cell.trim())
          .filter((cell) => cell !== '')
        content.push(cells)

        // Check if next line is separator
        if (i + 1 < lines.length && lines[i + 1]?.trim().match(/^\|[\|\s\-:]+\|$/)) {
          withHeadings = true
          i++ // Skip separator line
        }
      } else {
        break
      }
      i++
    }

    const block = editor.blocks.insert('table')
    editor.blocks.update(
      block.id,
      {
        content: content,
        withHeadings: withHeadings,
      },
      tunes,
    )

    return block
  }

  static isItMarkdownExported(markdown: string): boolean {
    return markdown.startsWith('|')
  }
}
