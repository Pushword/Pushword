import ListTool from '@editorjs/nested-list'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { BlockToolData, API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import Raw from '../Raw/Raw'

export interface ListData extends BlockToolData {
  style?: 'ordered' | 'unordered'
  items?: any[]
}

export default class List extends ListTool {
  static async exportToMarkdown(data: ListData, tunes?: BlockTuneData): Promise<string> {
    if (!data || !data.items) {
      return ''
    }

    const isOrdered = data.style === 'ordered'
    const markdown = List._itemsToMarkdown(data.items, isOrdered, 0)
    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown)
    return MarkdownUtils.addAttributes(formattedMarkdown, tunes)
  }

  private static _itemsToMarkdown(
    items: any[],
    isOrdered: boolean,
    depth: number,
  ): string {
    if (!items || items.length === 0) {
      return ''
    }

    const indent = '  '.repeat(depth)
    let markdown = ''

    items.forEach((item, index) => {
      if (isOrdered) {
        markdown += `${indent}${index + 1}. ${item.content || item}\n`
      } else {
        markdown += `${indent}- ${item.content || item}\n`
      }

      if (item.items && item.items.length > 0) {
        markdown += List._itemsToMarkdown(item.items, isOrdered, depth + 1)
      }
    })

    markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown)
    return markdown
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
    let currentItem: { content: string; items: any[] } | null = null
    let isOrdered: boolean | null = null

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
      const rawContent: string = orderedMatch ? orderedMatch[2] : unorderedMatch[1]
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
      currentItem = { content: content, items: [] }

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
        style: isOrdered ? 'ordered' : 'unordered',
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
