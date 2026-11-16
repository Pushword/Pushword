import type EditorJS from '@editorjs/editorjs'

const HIGHLIGHT = 'pw-paragraph-selection'

/** Kept separately so native input stays inside one editing host; history saves both endpoints. */
export const paragraphSelections = new WeakMap<HTMLElement, Range>()

/** Browsers keep native editing inside one contenteditable, even with a cross-field range. */
export function setupParagraphSelection(editor: EditorJS, holder: HTMLElement, signal: AbortSignal): void {
  const options = { capture: true, signal }
  const paragraphAt = (node: Node | null): HTMLElement | null => {
    const element = node instanceof Element ? node : node?.parentElement
    const paragraph = element?.closest<HTMLElement>('.ce-paragraph[contenteditable="true"]')
    return paragraph && holder.contains(paragraph) ? paragraph : null
  }
  const paragraphsBetween = (first: HTMLElement, last: HTMLElement): HTMLElement[] | null => {
    const blocks = Array.from(holder.querySelectorAll('.ce-block'))
    const ends = [blocks.indexOf(first.closest('.ce-block')!), blocks.indexOf(last.closest('.ce-block')!)]
    const fields = blocks.slice(Math.min(...ends), Math.max(...ends) + 1)
      .map(block => block.querySelector<HTMLElement>('.ce-paragraph[contenteditable="true"]'))
    return fields.every(field => field !== null) ? fields as HTMLElement[] : null
  }
  const selectedRange = (): Range | null => {
    const selection = window.getSelection()
    if (editor.readOnly.isEnabled) return null
    const range = paragraphSelections.get(holder) ??
      (selection?.rangeCount && !selection.isCollapsed ? selection.getRangeAt(0) : null)
    if (!range) return null
    const first = paragraphAt(range.startContainer)
    const last = paragraphAt(range.endContainer)
    return first && last && first !== last && paragraphsBetween(first, last) ? range : null
  }

  let highlight: Highlight | null = null
  const paint = (): void => {
    if (highlight && CSS.highlights.get(HIGHLIGHT) === highlight) CSS.highlights.delete(HIGHLIGHT)
    const range = selectedRange()
    highlight = range ? new Highlight(range) : null
    if (highlight) CSS.highlights.set(HIGHLIGHT, highlight)
  }
  const select = (range: Range | null): void => {
    if (range) {
      paragraphSelections.set(holder, range)
      window.getSelection()?.collapse(range.startContainer, range.startOffset)
    } else paragraphSelections.delete(holder)
    paint()
  }
  document.addEventListener('selectionchange', () => {
    const selection = window.getSelection()
    const native = selection?.rangeCount && !selection.isCollapsed ? selection.getRangeAt(0) : null
    const first = native && paragraphAt(native.startContainer)
    const last = native && paragraphAt(native.endContainer)
    if (native && first && last && first !== last && paragraphsBetween(first, last)) {
      select(native.cloneRange())
    } else if (!selectedRange() || (native && !anchor)) select(null)
    else {
      const range = paragraphSelections.get(holder)
      if (native && range) selection!.collapse(range.startContainer, range.startOffset)
      paint()
    }
  }, { signal })
  document.addEventListener('mousedown', () => select(null), options)
  signal.addEventListener('abort', () => {
    paragraphSelections.delete(holder)
    if (highlight && CSS.highlights.get(HIGHLIGHT) === highlight) CSS.highlights.delete(HIGHLIGHT)
  }, { once: true })

  let anchor: { node: Node; offset: number; field: HTMLElement } | null = null
  holder.addEventListener('mousedown', event => {
    anchor = null
    if (event.button !== 0 || editor.readOnly.isEnabled) return
    const field = paragraphAt(event.target as Node)
    const point = field && document.caretPositionFromPoint(event.clientX, event.clientY)
    if (field && point && field.contains(point.offsetNode)) {
      anchor = { node: point.offsetNode, offset: point.offset, field }
    }
  }, options)
  // Keep Editor.js from turning a text drag into whole-block selection.
  holder.addEventListener('mouseover', event => {
    const block = (event.target as Element).closest('.ce-block')
    const field = block?.querySelector<HTMLElement>('.ce-paragraph[contenteditable="true"]')
    if (anchor && field && paragraphsBetween(anchor.field, field)) event.stopPropagation()
  }, options)
  const extend = (event: MouseEvent): void => {
    if (!anchor || !anchor.node.isConnected) return
    // The inline toolbar can otherwise cover the text under the drag endpoint.
    editor.inlineToolbar.close()
    const point = document.caretPositionFromPoint(event.clientX, event.clientY)
    const field = point && paragraphAt(point.offsetNode)
    if (!point || !field || !paragraphsBetween(anchor.field, field)) return
    if (field === anchor.field && !selectedRange()) return
    event.preventDefault()
    if (field === anchor.field) {
      select(null)
      window.getSelection()?.setBaseAndExtent(anchor.node, anchor.offset, point.offsetNode, point.offset)
      return
    }
    const range = document.createRange()
    range.setStart(anchor.node, anchor.offset)
    range.collapse(true)
    if (range.comparePoint(point.offsetNode, point.offset) < 0) {
      range.setStart(point.offsetNode, point.offset)
      range.setEnd(anchor.node, anchor.offset)
    } else range.setEnd(point.offsetNode, point.offset)
    select(range)
  }
  document.addEventListener('mousemove', event => {
    if (event.buttons === 1) extend(event)
  }, options)
  document.addEventListener('mouseup', event => {
    // Firefox's native drag clamps the range after mousemove; restore its final endpoint.
    extend(event)
    anchor = null
  }, { signal })

  const replace = (text: string): void => {
    const range = selectedRange()
    if (!range) return
    const first = paragraphAt(range.startContainer)!
    const last = paragraphAt(range.endContainer)!
    const fields = paragraphsBetween(first, last)!
    const before = document.createRange()
    before.selectNodeContents(first)
    before.setEnd(range.startContainer, range.startOffset)
    const after = document.createRange()
    after.selectNodeContents(last)
    after.setStart(range.endContainer, range.endOffset)
    const prefix = before.cloneContents()
    const suffix = after.cloneContents()
    paragraphSelections.delete(holder)
    window.getSelection()!.removeAllRanges()
    const inserted = document.createTextNode(text)
    first.replaceChildren(prefix, inserted, suffix)
    const caret = document.createRange()
    caret.setStart(inserted, inserted.length)
    caret.collapse(true)
    for (const field of fields.slice(1).reverse()) {
      editor.blocks.delete(editor.blocks.getBlockIndex(field.closest<HTMLElement>('.ce-block')!.dataset.id!))
    }
    first.focus({ preventScroll: true })
    window.getSelection()!.removeAllRanges()
    window.getSelection()!.addRange(caret)
    paint()
  }

  holder.addEventListener('keydown', event => {
    const range = selectedRange()
    if (!range || event.isComposing) return
    const erases = event.key === 'Backspace' || (event.key === 'Delete' && !event.shiftKey)
    if (erases) {
      event.preventDefault()
      event.stopImmediatePropagation()
      replace('')
    } else if (['ArrowLeft', 'ArrowRight', 'Escape'].includes(event.key)) {
      const end = event.key === 'ArrowRight'
      const node = end ? range.endContainer : range.startContainer
      paragraphAt(node)!.focus({ preventScroll: true })
      window.getSelection()?.collapse(node, end ? range.endOffset : range.startOffset)
      select(null)
      event.preventDefault()
      event.stopImmediatePropagation()
    } else if (event.key === 'Enter') replace('')
    else if (['ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown', 'Tab'].includes(event.key)) select(null)
    else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') select(null)
    else if (event.key.length === 1 || (event.key === 'Delete' && event.shiftKey)) {
      // Preserve the range until native input or clipboard events consume it.
      event.stopImmediatePropagation()
    }
  }, options)
  holder.addEventListener('beforeinput', event => {
    const input = event as InputEvent
    if (!selectedRange()) return
    const erases = input.inputType === 'deleteContentBackward' || input.inputType === 'deleteContentForward'
    if (!erases && !['insertText', 'insertReplacementText', 'insertCompositionText'].includes(input.inputType)) return
    if (!input.cancelable) {
      if (input.inputType === 'insertCompositionText') replace('')
      return
    }
    event.preventDefault()
    event.stopImmediatePropagation()
    replace(erases ? '' : input.data ?? '')
  }, options)

  // Register after Undo and before ClipboardManager, so cuts remain one history entry.
  for (const type of ['copy', 'cut']) {
    document.addEventListener(type, event => {
      const range = selectedRange()
      const clipboard = (event as ClipboardEvent).clipboardData
      if (!range || !clipboard || !(event.target instanceof Node) || !holder.contains(event.target)) return
      const first = paragraphAt(range.startContainer)!
      const last = paragraphAt(range.endContainer)!
      // Firefox cannot clone a native range across separate editing hosts.
      const paragraphs = paragraphsBetween(first, last)!.map(field => {
        const part = document.createRange()
        part.selectNodeContents(field)
        if (field === first) part.setStart(range.startContainer, range.startOffset)
        if (field === last) part.setEnd(range.endContainer, range.endOffset)
        const paragraph = document.createElement('p')
        paragraph.append(part.cloneContents())
        return paragraph
      })
      clipboard.setData('text/plain', paragraphs.map(field => field.textContent).join('\n\n'))
      clipboard.setData('text/html', paragraphs.map(field => field.outerHTML).join(''))
      event.preventDefault()
      event.stopImmediatePropagation()
      if (type === 'cut') replace('')
    }, options)
  }
  document.addEventListener('paste', event => {
    if (event.target instanceof Node && holder.contains(event.target)) replace('')
  }, options)
}
