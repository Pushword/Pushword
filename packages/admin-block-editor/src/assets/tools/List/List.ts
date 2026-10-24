import ListTool, { type ListParams } from '@editorjs/list'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { BlockToolData, API, BlockAPI } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import Raw from '../Raw/Raw'

export type ListStyle = 'ordered' | 'unordered' | 'checklist'

export interface ListData extends BlockToolData {
  style?: ListStyle
  meta?: Record<string, unknown>
  items?: any[]
}

/** An item handed to pasteItems(): sanitized HTML, and its depth in the source. */
export interface PastedItem {
  content: string
  depth: number
}

interface FlatItem {
  content: string
  meta: Record<string, unknown>
  depth: number
}

/** The part of @editorjs/list's tabulator used here; the package types it private. */
interface Tabulator {
  data: Required<ListData>
  listWrapper: HTMLElement
  render(): HTMLElement
  save(): Required<ListData>
  mergeItemWithPrevious(item: Element): void
}

/** Two trailing spaces or a backslash: the line ends with a hard break. */
const HARD_BREAK_END = /(?: {2,}|\\)$/
const ITEM = '.cdx-list__item'
const ITEM_CONTENT = '.cdx-list__item-content'
/** The CSS highlight painting the items selected across a list. */
const SELECTED_ITEMS = 'pw-list-items'

function html(fragment: DocumentFragment): string {
  // A lone <br> is the browser's filler for an emptied field, not content.
  if (!fragment.textContent?.trim() && !fragment.querySelector(':not(br)')) return ''
  const container = document.createElement('div')
  container.append(fragment)
  return container.innerHTML
}

function textLength(content: string): number {
  const template = document.createElement('template')
  template.innerHTML = content
  return template.content.textContent?.length ?? 0
}

/** An item's text on one line, a line break only where it holds a <br>. */
function plainText(content: string): string {
  const template = document.createElement('template')
  template.innerHTML = content.replace(/\n/g, ' ')
  template.content.querySelectorAll('br').forEach((br) => br.replaceWith('\n'))
  return template.content.textContent ?? ''
}

function placeCaret(field: HTMLElement, offset: number): void {
  field.focus()
  const range = document.createRange()
  range.setStart(field, 0)
  const walker = document.createTreeWalker(field, NodeFilter.SHOW_TEXT)
  let left = offset
  while (walker.nextNode()) {
    const text = walker.currentNode as Text
    if (left <= text.length) {
      range.setStart(text, left)
      break
    }
    left -= text.length
    range.setStartAfter(text)
  }
  range.collapse(true)
  const selection = window.getSelection()!
  selection.removeAllRanges()
  selection.addRange(range)
}

/** Whether only collapsible whitespace follows the caret in the field, as Editor.js measures it. */
function caretAtEnd(field: Element): boolean {
  const selection = window.getSelection()
  if (!selection?.isCollapsed || !selection.rangeCount) return false
  const range = selection.getRangeAt(0).cloneRange()
  if (!field.contains(range.endContainer)) return false
  range.setEnd(field, field.childNodes.length)
  const rest = range.cloneContents()
  return !/[^\t\n\r ]/.test(rest.textContent ?? '') && !rest.querySelector('img')
}

export default class List extends ListTool {
  private readonly blockApi: BlockAPI
  private readonly isReadOnly: boolean
  private itemSelection: { anchor: Element; focus: Element; listeners: AbortController } | null = null
  private dragListeners = new AbortController()

  constructor(params: ListParams) {
    super(params)
    this.blockApi = params.block
    this.isReadOnly = params.readOnly
  }

  private get tabulator(): Tabulator {
    return (this as unknown as { list: Tabulator }).list
  }

  render(): HTMLElement {
    const element = super.render()
    // On the block, as switching the list style renders a new list element.
    // Not from rendered(): blocks.update(), the markdown import's path, never
    // calls it. The holder exists once Editor.js has composed the block,
    // right after this returns.
    if (this.isReadOnly) return element

    queueMicrotask(() => {
      this.blockApi.holder.addEventListener('keydown', (event) => this.deleteForward(event), true)
      this.blockApi.holder.addEventListener('mousedown', (event) => this.selectItemsWithMouse(event), true)
    })
    return element
  }

  destroy(): void {
    this.clearItemSelection()
    this.dragListeners.abort()
  }

  /** The item of this list holding the node, when the node sits in that part of it. */
  private itemAt(node: EventTarget | null, part = ITEM): Element | null {
    if (!(node instanceof Node)) return null
    const element = node instanceof Element ? node : node.parentElement
    const item = element?.closest(part)?.closest(ITEM)
    return item && this.tabulator.listWrapper.contains(item) ? item : null
  }

  /**
   * Each item is a field of its own, and a browser keeps a selection inside
   * the field it started in. Dragging from an item onto others, or a
   * Shift+click away from the caret's item, selects whole items instead, the
   * way Editor.js selects whole blocks once a drag crosses into another one.
   */
  private selectItemsWithMouse(event: MouseEvent): void {
    const item = this.itemAt(event.target, ITEM_CONTENT)
    if (event.button !== 0 || !item || !('highlights' in CSS)) return
    if (event.shiftKey) {
      const anchor = this.itemSelection?.anchor ?? this.itemAt(window.getSelection()?.anchorNode ?? null, ITEM_CONTENT)
      if (!anchor || anchor === item) return
      event.preventDefault()
      this.selectItems(anchor, item)
      return
    }

    this.dragListeners.abort()
    const drag = this.dragListeners = new AbortController()
    const options = { signal: drag.signal }
    document.addEventListener('mouseover', ({ target }) => {
      const over = this.itemAt(target)
      if (over && (over !== item || this.itemSelection)) this.selectItems(item, over)
      // Over another block, Editor.js selects the blocks.
      else if (!over && !this.blockApi.holder.contains(target as Node)) this.clearItemSelection()
    }, options)
    // Chromium goes on selecting the first item's text as the drag moves.
    const dropTextSelection = (): void => {
      const selection = window.getSelection()!
      if (this.itemSelection && selection.rangeCount > 0) selection.removeAllRanges()
    }
    document.addEventListener('selectionchange', dropTextSelection, options)
    document.addEventListener('mouseup', () => {
      dropTextSelection()
      drag.abort()
    }, options)
  }

  /**
   * Painted through the CSS Highlight API: a class on the items would be a
   * change to the block for Editor.js, and a selection a change to the page.
   */
  private selectItems(anchor: Element, focus: Element): void {
    window.getSelection()?.removeAllRanges()
    this.itemSelection = { anchor, focus, listeners: this.itemSelection?.listeners ?? this.listenToSelectedItems() }
    const [start, end] = this.selectedItems()
    const items = Array.from(this.tabulator.listWrapper.querySelectorAll(ITEM)).slice(start, end + 1)
    CSS.highlights.set(SELECTED_ITEMS, new Highlight(...items.map((selected) => {
      const range = document.createRange()
      range.selectNodeContents(selected.querySelector(ITEM_CONTENT)!)
      return range
    })))
  }

  /** On the window, in the capture phase: selected items own the keys and the clipboard before anything else. */
  private listenToSelectedItems(): AbortController {
    const listeners = new AbortController()
    const options = { capture: true, signal: listeners.signal }
    window.addEventListener('keydown', (event) => this.selectedItemsKey(event), options)
    window.addEventListener('copy', (event) => this.copySelectedItems(event), options)
    window.addEventListener('cut', (event) => this.copySelectedItems(event), options)
    // The paste then lands in the item left in their place, as into any other.
    window.addEventListener('paste', () => this.replaceSelectedItems(''), options)
    window.addEventListener('mousedown', (event) => {
      if (!event.shiftKey || !this.itemAt(event.target, ITEM_CONTENT)) this.clearItemSelection()
    }, options)
    return listeners
  }

  private clearItemSelection(): void {
    if (!this.itemSelection) return
    this.itemSelection.listeners.abort()
    this.itemSelection = null
    CSS.highlights.delete(SELECTED_ITEMS)
  }

  /** Flat indices of the first and last selected items. */
  private selectedItems(): [number, number] {
    const items = Array.from(this.tabulator.listWrapper.querySelectorAll(ITEM))
    const ends = [items.indexOf(this.itemSelection!.anchor), items.indexOf(this.itemSelection!.focus)]
    return [Math.min(...ends), Math.max(...ends)]
  }

  /**
   * Delete, Backspace and typing act on the selected items, as on selected
   * blocks; any other key acts on a caret, put back at the end of the item
   * the selection reached.
   */
  private selectedItemsKey(event: KeyboardEvent): void {
    if (event.isComposing || ['Shift', 'Control', 'Alt', 'AltGraph', 'Meta'].includes(event.key)) return
    if (event.ctrlKey || event.metaKey) {
      // The clipboard event that follows takes the selection.
      if (['c', 'x', 'v'].includes(event.key.toLowerCase())) return
    } else if (event.key === 'Delete' || event.key === 'Backspace' || (event.key.length === 1 && !event.altKey)) {
      event.preventDefault()
      event.stopImmediatePropagation()
      this.replaceSelectedItems(event.key.length === 1 ? event.key : null)
      return
    }
    const field = this.itemSelection!.focus.querySelector<HTMLElement>(ITEM_CONTENT)!
    this.clearItemSelection()
    placeCaret(field, field.textContent?.length ?? 0)
  }

  /**
   * Copy the selected items, their levels relative to the first: a line
   * each as plain text, a list as HTML, and Editor.js' own block data, which
   * a paste into another list item reads levels included.
   */
  private copySelectedItems(event: ClipboardEvent): void {
    if (!event.clipboardData) return
    const [start, end] = this.selectedItems()
    const data = this.tabulator.save()
    const flat = List.nestable(List.flatten(data.items, 0).slice(start, end + 1))
    const items = List.nest(flat)
    event.clipboardData.setData('text/plain', flat.map(({ content }) => plainText(content)).join('\n'))
    event.clipboardData.setData('text/html', List.itemsHtml(items, data.style === 'ordered' ? 'ol' : 'ul'))
    event.clipboardData.setData('application/x-editor-js', JSON.stringify([{ tool: this.blockApi.name, data: { ...data, items } }]))
    event.preventDefault()
    event.stopImmediatePropagation()
    if (event.type === 'cut') this.replaceSelectedItems(null)
  }

  /**
   * Remove the selected items, or put one item holding the text in their
   * place, the caret after it. Children whose parent went keep their
   * siblings, under the nearest item above.
   */
  private replaceSelectedItems(text: string | null): void {
    const [start, end] = this.selectedItems()
    this.clearItemSelection()
    const data = this.tabulator.save()
    const flat = List.flatten(data.items, 0)
    const [first] = flat.splice(start, end - start + 1)
    if (text !== null || flat.length === 0) {
      const span = document.createElement('span')
      span.textContent = text ?? ''
      flat.splice(start, 0, { content: span.innerHTML, meta: List.newItemMeta(data.style), depth: first!.depth })
    }
    const items = this.renderItems(List.nestable(flat)).querySelectorAll(ITEM)
    // After a removal, the caret goes to the start of the item that followed, or the end of the last.
    const index = Math.min(start, items.length - 1)
    const field = items[index]!.querySelector<HTMLElement>(ITEM_CONTENT)!
    placeCaret(field, index === start ? (text?.length ?? 0) : (field.textContent?.length ?? 0))
  }

  /**
   * Delete at the end of an item brings the next one up, as a word processor
   * does: @editorjs/list only handles Backspace, and Editor.js merges from the
   * end of the last item only. Bringing item N+1 up is the Backspace at its
   * start, so the package's own merge does it, children included.
   */
  private deleteForward(event: KeyboardEvent): void {
    if (event.key !== 'Delete' || event.defaultPrevented || event.isComposing ||
      event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) return
    const field = (event.target as Element).closest?.(ITEM_CONTENT)
    if (!field || !caretAtEnd(field)) return
    const items = Array.from(this.tabulator.listWrapper.querySelectorAll(ITEM))
    const next = items[items.indexOf(field.closest(ITEM)!) + 1]
    if (next === undefined) return
    event.preventDefault()
    event.stopPropagation()
    this.tabulator.mergeItemWithPrevious(next)
  }

  /**
   * Insert pasted items at the caret, the way a word processor pastes
   * paragraphs: the first joins the text before the caret, the last takes the
   * text after it, so pasting into an empty item leaves no empty item behind.
   * Depths are kept relative to the first item and placed under the caret's
   * item. Called through BlockAPI.call() by the clipboard handler, which has
   * already sanitized the content.
   */
  pasteItems({ items }: { items: PastedItem[] }): void {
    const selection = window.getSelection()
    const range = selection?.rangeCount ? selection.getRangeAt(0) : null
    const container = range?.commonAncestorContainer
    const field = (container instanceof Element ? container : container?.parentElement)?.closest(ITEM_CONTENT)
    const wrapper = this.tabulator.listWrapper
    if (!range || !field || !wrapper.contains(field) || items.length === 0) return

    range.deleteContents()
    const before = document.createRange()
    before.setStart(field, 0)
    before.setEnd(range.startContainer, range.startOffset)
    const after = document.createRange()
    after.setStart(range.startContainer, range.startOffset)
    after.setEnd(field, field.childNodes.length)

    const data = this.tabulator.save()
    const flat = List.flatten(data.items, 0)
    const index = Array.from(wrapper.querySelectorAll(ITEM)).indexOf(field.closest(ITEM)!)
    const current = flat[index]!
    const pasted: FlatItem[] = List.nestable(items).map((item) => (
      { content: item.content, meta: List.newItemMeta(data.style), depth: current.depth + item.depth }
    ))
    pasted[0] = { ...current, content: html(before.cloneContents()) + pasted[0]!.content }
    const last = pasted[pasted.length - 1]!
    const caretOffset = textLength(last.content)
    last.content += html(after.cloneContents())
    flat.splice(index, 1, ...pasted)

    const target = this.renderItems(flat).querySelectorAll(ITEM)[index + pasted.length - 1]!
    placeCaret(target.querySelector<HTMLElement>(ITEM_CONTENT)!, caretOffset)
  }

  /** Render the list anew with these items in place of its own. */
  private renderItems(flat: FlatItem[]): HTMLElement {
    const wrapper = this.tabulator.listWrapper
    this.tabulator.data.items = List.nest(flat)
    const list = this.tabulator.render()
    wrapper.replaceWith(list)
    ;(this as unknown as { listElement: HTMLElement }).listElement = list
    return list
  }

  private static newItemMeta(style: ListStyle): Record<string, unknown> {
    return style === 'checklist' ? { checked: false } : {}
  }

  /**
   * Depths a list can hold: each item one level under the nearest item
   * above it that was shallower, at the top when there is none. Items
   * without their parent keep their siblings and their own children.
   */
  private static nestable<T extends { depth: number }>(items: T[]): T[] {
    const parents: { from: number; to: number }[] = []
    return items.map((item) => {
      while (parents.length > 0 && parents[parents.length - 1]!.from >= item.depth) parents.pop()
      const depth = parents.length > 0 ? parents[parents.length - 1]!.to + 1 : 0
      parents.push({ from: item.depth, to: depth })
      return { ...item, depth }
    })
  }

  private static itemsHtml(items: any[], tag: 'ol' | 'ul'): string {
    const children = (item: any): string => (item.items.length > 0 ? List.itemsHtml(item.items, tag) : '')
    return `<${tag}>${items.map((item) => `<li>${item.content}${children(item)}</li>`).join('')}</${tag}>`
  }

  private static flatten(items: any[], depth: number): FlatItem[] {
    return items.flatMap((item) => [
      { content: item.content ?? '', meta: item.meta ?? {}, depth },
      ...List.flatten(item.items ?? [], depth + 1),
    ])
  }

  private static nest(flat: FlatItem[]): any[] {
    const root: any[] = []
    const levels = [root]
    for (const { content, meta, depth } of flat) {
      const item = { content, meta, items: [] }
      levels.length = depth + 1
      levels[depth]!.push(item)
      levels.push(item.items)
    }
    return root
  }

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

  private static _itemsToMarkdown(items: any[], style: ListStyle, indentation: number): string {
    if (!items || items.length === 0) {
      return ''
    }

    const indent = ' '.repeat(indentation)
    let markdown = ''

    items.forEach((item, index) => {
      const marker = List._marker(style, item, index)
      const content: string = typeof item === 'string' ? item : (item.content ?? '')
      // A <br> is a hard break; a newline kept from the source stays soft.
      // Either way the next line sits under the item's text.
      const text = MarkdownUtils.convertInlineHtmlToMarkdown(
        MarkdownUtils.htmlLines(content).join('  \n'),
      )
      const textIndent = indent + ' '.repeat(marker.length + 1)
      markdown += `${indent}${marker} ${text.replace(/\n/g, '\n' + textIndent)}\n`

      if (item.items && item.items.length > 0) {
        // Children align with the parent's text; checkbox markers are part of that text.
        const markerWidth = style === 'ordered' ? marker.length + 1 : 2
        markdown += List._itemsToMarkdown(item.items, style, indentation + markerWidth)
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
