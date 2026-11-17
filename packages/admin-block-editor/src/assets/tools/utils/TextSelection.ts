import type EditorJS from '@editorjs/editorjs'
import { replaceTextSelection, textSelectionClipboard } from './TextSelectionContent'
import { fieldRange, selectedBlocks, textFieldAt, textFields, textSelections, wholeBlockSelected } from './TextSelectionState'

const HIGHLIGHT = 'pw-text-selection'
interface Point { node: Node; offset: number; block: HTMLElement; atomic?: boolean }

function blockPoint(block: HTMLElement, after: boolean): Point {
  return { node: block.parentNode!, offset: Array.from(block.parentNode!.childNodes).indexOf(block) + Number(after), block, atomic: true }
}

export function setupTextSelection(editor: EditorJS, holder: HTMLElement, signal: AbortSignal): void {
  const options = { capture: true, signal }
  let dragging: Point | null = null
  let origin: Point | null = null
  let focus: Point | null = null
  let highlight: Highlight | null = null

  const point = (node: Node | null, offset: number): Point | null => {
    const field = textFieldAt(node, holder)
    if (field && node) return { node, offset, block: field.closest<HTMLElement>('.ce-block')! }
    const block = node?.childNodes[offset] ?? node?.childNodes[offset - 1]
    return node && block instanceof HTMLElement && block.matches('.ce-block') && holder.contains(block)
      ? { node, offset, block, atomic: true } : null
  }
  const selectedRange = (): Range | null => {
    if (editor.readOnly.isEnabled) return null
    const stored = textSelections.get(holder)
    if (stored && !stored.collapsed && holder.contains(stored.startContainer) && holder.contains(stored.endContainer)) return stored
    const native = window.getSelection()
    if (!native?.rangeCount || native.isCollapsed) return null
    const range = native.getRangeAt(0)
    const first = textFieldAt(range.startContainer, holder)
    const last = textFieldAt(range.endContainer, holder)
    return first && last && first !== last ? range : null
  }
  const paint = (): void => {
    if (highlight && CSS.highlights.get(HIGHLIGHT) === highlight) CSS.highlights.delete(HIGHLIGHT)
    holder.querySelectorAll('.pw-text-selection-block').forEach(block => block.classList.remove('pw-text-selection-block'))
    const range = selectedRange()
    if (!range) { highlight = null; return }
    highlight = new Highlight(...textFields(holder).filter(field => range.intersectsNode(field)).map(field => fieldRange(field, range)))
    CSS.highlights.set(HIGHLIGHT, highlight)
    for (const block of selectedBlocks(holder, range)) {
      if (wholeBlockSelected(block, range) || textFields(block).length === 0) block.classList.add('pw-text-selection-block')
    }
  }
  const clear = (): void => {
    textSelections.delete(holder)
    origin = focus = null
    paint()
  }
  const select = (start: Point, end: Point): void => {
    const first = textFieldAt(start.node, holder)
    const last = textFieldAt(end.node, holder)
    const selection = window.getSelection()!
    if (first && first === last) {
      clear()
      first.focus({ preventScroll: true })
      selection.setBaseAndExtent(start.node, start.offset, end.node, end.offset)
      return
    }
    const range = document.createRange()
    range.setStart(start.node, start.offset)
    range.collapse(true)
    if (range.comparePoint(end.node, end.offset) < 0) {
      range.setStart(end.node, end.offset)
      range.setEnd(start.node, start.offset)
    } else range.setEnd(end.node, end.offset)
    origin = start
    focus = end
    textSelections.set(holder, range)
    holder.querySelectorAll('.ce-block--selected').forEach(block => block.classList.remove('ce-block--selected'))
    // A collapsed native caret keeps IME and Firefox's native input inside one editing host.
    const field = first ?? last
    if (field) {
      field.focus({ preventScroll: true })
      if (first) selection.collapse(start.node, start.offset)
      else selection.collapse(end.node, end.offset)
    } else {
      start.block.tabIndex = -1
      start.block.focus({ preventScroll: true })
      selection.removeAllRanges()
    }
    editor.inlineToolbar.close()
    paint()
  }

  document.addEventListener('selectionchange', () => {
    if (editor.readOnly.isEnabled) { clear(); return }
    const selection = window.getSelection()
    if (!selection) return
    const first = point(selection.anchorNode, selection.anchorOffset)
    const last = point(selection.focusNode, selection.focusOffset)
    const stored = textSelections.get(holder)
    if (!selection.isCollapsed && first && last &&
      ((textFieldAt(first.node, holder) && textFieldAt(last.node, holder) &&
        textFieldAt(first.node, holder) !== textFieldAt(last.node, holder)) || stored)) {
      select(first, last)
    } else if (stored && (!origin || !focus) && selectedRange()) {
      const start = point(stored.startContainer, stored.startOffset)
      const end = point(stored.endContainer, stored.endOffset)
      if (start && end) select(start, end)
    } else if (!selectedRange() || (!selection.isCollapsed && !dragging)) clear()
    else if (!selection.isCollapsed && origin && focus) select(origin, focus)
    else paint()
  }, { signal })
  document.addEventListener('mousedown', event => {
    if (!event.shiftKey || !textFieldAt(event.target as Node, holder)) clear()
  }, options)
  signal.addEventListener('abort', () => {
    textSelections.delete(holder)
    if (highlight && CSS.highlights.get(HIGHLIGHT) === highlight) CSS.highlights.delete(HIGHLIGHT)
    holder.querySelectorAll('.pw-text-selection-block').forEach(block => block.classList.remove('pw-text-selection-block'))
  }, { once: true })

  const cursorPoint = (event: MouseEvent): Point | null => {
    const caret = document.caretPositionFromPoint(event.clientX, event.clientY)
    return caret ? point(caret.offsetNode, caret.offset) : null
  }
  holder.addEventListener('mousedown', event => {
    dragging = null
    if (event.button !== 0 || editor.readOnly.isEnabled) return
    const end = cursorPoint(event)
    if (event.shiftKey && end) {
      const native = window.getSelection()
      const start = origin ?? (native && point(native.anchorNode, native.anchorOffset))
      if (start) {
        event.preventDefault()
        event.stopPropagation()
        dragging = start
        select(start, end)
        return
      }
    }
    if (end) { dragging = end; return }
    const target = event.target as Element
    const block = target.closest<HTMLElement>('.ce-block')
    if (!block || target.closest('button, input, select') ||
      (target.closest('textarea') && !target.closest('.monaco-editor'))) return
    if (!textFields(block).length || target.closest('img, .image-tool__image, .pw-mermaid-preview, .editorjs-monaco-wrapper')) {
      dragging = blockPoint(block, false)
    }
  }, options)
  holder.addEventListener('mouseover', event => {
    if (dragging && (event.target as Element).closest('.ce-block')) event.stopPropagation()
  }, options)
  const extend = (event: MouseEvent): void => {
    if (!dragging || !dragging.node.isConnected) return
    editor.inlineToolbar.close()
    let end = cursorPoint(event)
    const target = (event.target as Element).closest<HTMLElement>('.ce-block')
    if (!end && target && holder.contains(target) && target !== dragging.block) {
      const forward = Boolean(dragging.block.compareDocumentPosition(target) & Node.DOCUMENT_POSITION_FOLLOWING)
      end = blockPoint(target, forward)
    }
    if (!end) return
    if (dragging.atomic && end.block === dragging.block) return
    if (textFieldAt(dragging.node, holder) === textFieldAt(end.node, holder) && !selectedRange()) return
    let start = dragging
    if (dragging.atomic) {
      const backward = Boolean(dragging.block.compareDocumentPosition(end.block) & Node.DOCUMENT_POSITION_PRECEDING)
      start = blockPoint(dragging.block, backward)
    }
    event.preventDefault()
    select(start, end)
  }
  document.addEventListener('mousemove', event => { if (event.buttons === 1) extend(event) }, options)
  document.addEventListener('mouseup', event => { extend(event); dragging = null }, { signal })

  const replace = (text: string): void => {
    const range = selectedRange()
    if (!range) return
    replaceTextSelection(editor, holder, range, text)
    clear()
  }
  const keyboardFocus = (event: KeyboardEvent): boolean => {
    if (!event.shiftKey || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return false
    const native = window.getSelection()!
    const start = origin ?? point(native.anchorNode, native.anchorOffset)
    const current = focus ?? point(native.focusNode, native.focusOffset)
    if (!start || !current) return false
    const field = textFieldAt(current.node, holder)
    if (!field) return false
    editor.inlineToolbar.close()
    const forward = ['ArrowRight', 'ArrowDown', 'End'].includes(event.key)
    const fields = textFields(holder)
    const index = fields.indexOf(field)
    let next: Point | null
    if (event.key === 'Home' || event.key === 'End') {
      const target = event.ctrlKey || event.metaKey ? fields[forward ? fields.length - 1 : 0]! : field
      next = { node: target, offset: forward ? target.childNodes.length : 0, block: target.closest<HTMLElement>('.ce-block')! }
    } else {
      const caret = document.createRange()
      caret.setStart(current.node, current.offset)
      caret.collapse(true)
      const rect = caret.getBoundingClientRect()
      const box = field.getBoundingClientRect()
      const vertical = event.key === 'ArrowUp' || event.key === 'ArrowDown'
      const lineHeight = parseFloat(getComputedStyle(field).lineHeight) || rect.height
      const outsideLine = vertical && (forward ? rect.bottom + lineHeight > box.bottom : rect.top - lineHeight < box.top)
      field.focus({ preventScroll: true })
      native.collapse(current.node, current.offset)
      let granularity: 'line' | 'word' | 'character' = 'character'
      if (vertical) granularity = 'line'
      else if (event.ctrlKey || event.metaKey) granularity = 'word'
      native.modify('move', forward ? 'forward' : 'backward', granularity)
      next = point(native.focusNode, native.focusOffset)
      if (outsideLine || (next?.node === current.node && next.offset === current.offset)) {
        const adjacent = fields[index + (forward ? 1 : -1)]
        if (adjacent) {
          adjacent.scrollIntoView({ block: 'nearest' })
          const box = adjacent.getBoundingClientRect()
          const line = parseFloat(getComputedStyle(adjacent).lineHeight) || rect.height
          const hit = vertical ? document.caretPositionFromPoint(Math.max(box.left + 1, Math.min(rect.x, box.right - 1)),
            forward ? box.top + line / 2 : box.bottom - line / 2) : null
          next = hit && adjacent.contains(hit.offsetNode) ? point(hit.offsetNode, hit.offset) :
            { node: adjacent, offset: forward ? 0 : adjacent.childNodes.length, block: adjacent.closest<HTMLElement>('.ce-block')! }
        }
      }
    }
    if (!next) return false
    event.preventDefault()
    event.stopImmediatePropagation()
    select(start, next)
    return true
  }
  holder.addEventListener('keydown', event => {
    if (event.isComposing || editor.readOnly.isEnabled || keyboardFocus(event)) return
    const range = selectedRange()
    if (!range) return
    const erases = event.key === 'Backspace' || (event.key === 'Delete' && !event.shiftKey)
    if (erases) {
      event.preventDefault()
      event.stopImmediatePropagation()
      replace('')
    } else if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Escape', 'Home', 'End', 'PageUp', 'PageDown', 'Tab'].includes(event.key)) {
      const end = ['ArrowRight', 'ArrowDown', 'End', 'PageDown'].includes(event.key)
      const node = end ? range.endContainer : range.startContainer
      const field = textFieldAt(node, holder)
      clear()
      field?.focus({ preventScroll: true })
      window.getSelection()?.collapse(node, end ? range.endOffset : range.startOffset)
      if (event.key !== 'Tab') { event.preventDefault(); event.stopImmediatePropagation() }
    } else if (event.key === 'Enter') replace('')
    else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') clear()
    else if (event.key.length === 1 || (event.key === 'Delete' && event.shiftKey)) {
      event.stopImmediatePropagation()
      if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey &&
        !(document.activeElement as HTMLElement)?.isContentEditable) {
        event.preventDefault()
        replace(event.key)
      }
    }
  }, options)
  holder.addEventListener('beforeinput', event => {
    const input = event as InputEvent
    if (!selectedRange()) return
    const erases = input.inputType === 'deleteContentBackward' || input.inputType === 'deleteContentForward'
    if (!erases && !['insertText', 'insertReplacementText', 'insertCompositionText'].includes(input.inputType)) return
    if (!input.cancelable) { if (input.inputType === 'insertCompositionText') replace(''); return }
    event.preventDefault()
    event.stopImmediatePropagation()
    replace(erases ? '' : input.data ?? '')
  }, options)
  for (const type of ['copy', 'cut']) {
    document.addEventListener(type, event => {
      const range = selectedRange()
      const clipboard = (event as ClipboardEvent).clipboardData
      if (!range || !clipboard || !(event.target instanceof Node) || !holder.contains(event.target)) return
      for (const [format, content] of Object.entries(textSelectionClipboard(holder, range))) clipboard.setData(format, content)
      event.preventDefault()
      event.stopImmediatePropagation()
      if (type === 'cut') replace('')
    }, options)
  }
  document.addEventListener('paste', event => {
    if (event.target instanceof Node && holder.contains(event.target)) replace('')
  }, options)
}
