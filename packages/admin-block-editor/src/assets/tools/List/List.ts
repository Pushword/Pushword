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

export default class List extends ListTool {
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
      markdown += `${indent}${List._marker(style, item, index)} ${item.content || item}\n`

      if (item.items && item.items.length > 0) {
        markdown += List._itemsToMarkdown(item.items, style, depth + 1)
      }
    })

    markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown)
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

    for (const line of lines) {
      const trimmedLine = line.trim()

      if (!trimmedLine) {
        if (currentItem !== null) {
          currentItem.content += '<br>'
        }
        continue
      }

      const orderedMatch = trimmedLine.match(/^(\d+)\.\s+(.*)/)
      const unorderedMatch = trimmedLine.match(/^[-*+]\s+(.*)/)

      // Check if this is a list item or continuation of previous content
      if (!orderedMatch && !unorderedMatch) {
        if (currentItem === null) {
          throw new Error('isItMarkdownExported not worked as expected')
        }
        // This is a continuation of the current item
        currentItem.content +=
          '<br>' + MarkdownUtils.convertInlineMarkdownToHtml(trimmedLine)
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
