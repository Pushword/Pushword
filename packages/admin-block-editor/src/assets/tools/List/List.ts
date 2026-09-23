import ListTool from '@editorjs/list'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { BlockToolData, API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import Raw from '../Raw/Raw'

export type ListStyle = 'ordered' | 'unordered' | 'checklist'

export interface ListData extends BlockToolData {
  style?: ListStyle
  meta?: Record<string, unknown>
  items?: any[]
}

/** Two trailing spaces or a backslash: the line ends with a hard break. */
const HARD_BREAK_END = /(?: {2,}|\\)$/

export default class List extends ListTool {
  /**
   * @editorjs/list declares no rule of its own, so Editor.js would clean every
   * item with the inline tools' rules alone and strip the <br> of a Shift+Enter.
   */
  static get sanitize() {
    return { items: { br: true } }
  }

  static async exportToMarkdown(data: ListData, tunes?: BlockTuneData): Promise<string> {
    if (!data || !data.items) {
      return ''
    }

    const markdown = List._itemsToMarkdown(data.items, data.style ?? 'unordered', 0)
    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown)
    return MarkdownUtils.addAttributes(formattedMarkdown, tunes)
  }

  private static _marker(style: ListStyle, item: any, index: number): string {
    switch (style) {
      case 'ordered':
        return `${index + 1}.`
      case 'checklist':
        return `- [${item.meta?.checked === true ? 'x' : ' '}]`
      default:
        return '-'
    }
  }

  private static _itemsToMarkdown(items: any[], style: ListStyle, depth: number): string {
    if (!items || items.length === 0) {
      return ''
    }

    const indent = '  '.repeat(depth)
    let markdown = ''

    items.forEach((item, index) => {
      const marker = List._marker(style, item, index)
      const content: string = typeof item === 'string' ? item : (item.content ?? '')
      // A <br> is a hard break; a newline kept from the source stays soft.
      // Either way the next line sits under the item's text.
      const text = MarkdownUtils.convertInlineHtmlToMarkdown(
        content.replace(/<br\s*\/?>/gi, '  \n'),
      )
      const textIndent = indent + ' '.repeat(marker.length + 1)
      markdown += `${indent}${marker} ${text.replace(/\n/g, '\n' + textIndent)}\n`

      if (item.items && item.items.length > 0) {
        markdown += List._itemsToMarkdown(item.items, style, depth + 1)
      }
    })

    return markdown
  }

  private static _style(hasCheckbox: boolean, isOrdered: boolean | null): ListStyle {
    if (hasCheckbox) {
      return 'checklist'
    }

    return isOrdered === true ? 'ordered' : 'unordered'
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    const tunes: BlockTuneData = result.tunes
    const markdownWithoutTunes = result.markdown

    // Split on raw newlines first: converting the whole block to HTML upfront
    // would turn every newline into <br> and collapse a "tight" list (items on
    // consecutive lines, no blank line between) into a single item. Inline
    // markdown is converted per item content below instead.
    const lines = markdownWithoutTunes.split('\n')

    const rootItems: any[] = []
    const stack: Array<{ items: any[]; depth: number }> = [
      { items: rootItems, depth: -1 },
    ]
    let currentItem: {
      content: string
      meta: Record<string, unknown>
      items: any[]
    } | null = null
    let isOrdered: boolean | null = null
    let hasCheckbox = false

    for (const [index, line] of lines.entries()) {
      const trimmedLine = line.trim()

      // Blank lines between items only make the list "loose", not a line break
      // in the item before; a continuation line after one reads it below.
      if (!trimmedLine) continue

      const orderedMatch = trimmedLine.match(/^(\d+)\.\s+(.*)/)
      const unorderedMatch = trimmedLine.match(/^[-*+]\s+(.*)/)

      // Check if this is a list item or continuation of previous content
      if (!orderedMatch && !unorderedMatch) {
        if (currentItem === null) {
          throw new Error('isItMarkdownExported not worked as expected')
        }
        // A continuation of the current item: after a blank line, a paragraph of
        // its own. The line break before it is a <br> only when hard; a soft
        // one stays a newline, shown and rendered as a space.
        const previousLine = lines[index - 1] ?? ''
        if (previousLine.trim() === '') currentItem.content += '<br>'
        const hardBreak = HARD_BREAK_END.test(previousLine)
        if (hardBreak) currentItem.content = currentItem.content.replace(/\\$/, '')
        currentItem.content +=
          (hardBreak ? '<br>' : '\n') +
          MarkdownUtils.convertInlineMarkdownToHtml(trimmedLine)
        continue
      }

      // This is a new list item
      const isCurrentOrdered = orderedMatch !== null

      // @ts-ignore
      let rawContent: string = orderedMatch ? orderedMatch[2] : unorderedMatch[1]

      // A task list marker turns the whole list into a checklist
      const checkboxMatch = rawContent.match(/^\[([ xX])\]\s+(.*)/)
      const meta: Record<string, unknown> = {}
      if (checkboxMatch && !isCurrentOrdered) {
        hasCheckbox = true
        meta['checked'] = checkboxMatch[1]!.toLowerCase() === 'x'
        rawContent = checkboxMatch[2]!
      }

      const content: string = MarkdownUtils.convertInlineMarkdownToHtml(rawContent)

      // first item permits to set isOrdered
      if (isOrdered === null) {
        isOrdered = isCurrentOrdered
      } else if (isOrdered !== isCurrentOrdered) {
        // Mixed list types - fallback to Raw because it's not supported
        return Raw.importFromMarkdown(editor, markdown)
      }

      // Calculate depth based on leading spaces
      const leadingSpaces = line.length - line.trimStart().length
      const currentDepth = Math.floor(leadingSpaces / 2)

      // Create new item
      currentItem = { content: content, meta, items: [] }

      // Find the correct parent level
      while (stack.length > 1 && stack[stack.length - 1]!.depth >= currentDepth) {
        stack.pop()
      }

      // Add item to parent's items array
      const parent = stack[stack.length - 1]
      if (!parent) {
        throw new Error('parent not found')
      }
      parent.items.push(currentItem)

      // Push this item onto the stack as potential parent
      stack.push({ items: currentItem.items, depth: currentDepth })
    }

    const block = editor.blocks.insert('list')

    editor.blocks.update(
      block.id,
      {
        style: List._style(hasCheckbox, isOrdered),
        meta: {},
        items: rootItems,
      },
      tunes,
    )
  }

  static isItMarkdownExported(markdown: string): boolean {
    return (
      markdown.trim().match(/^[-*+]\s/) !== null ||
      markdown.trim().match(/^\d+\.\s/) !== null
    )
  }
}
