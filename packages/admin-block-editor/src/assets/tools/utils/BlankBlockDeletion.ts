import type EditorJS from '@editorjs/editorjs'
import type { BlockAPI } from '@editorjs/editorjs'

function isBlankTextBlock(block: BlockAPI): boolean {
  if (block.name !== 'paragraph' && block.name !== 'header') return false
  // The heading's level selector contains text even when its editable heading is empty.
  const field = block.holder.querySelector(block.name === 'paragraph' ? '.ce-paragraph' : '.ce-header')
  return field !== null && !/[^\s\u200b]/u.test(field.textContent ?? '')
}

export function setupBlankBlockDeletion(editor: EditorJS, holder: HTMLElement, signal: AbortSignal): void {
  holder.addEventListener('keydown', event => {
    if (!['Delete', 'Backspace'].includes(event.key) || event.defaultPrevented || event.isComposing ||
      event.shiftKey || event.ctrlKey || event.altKey || event.metaKey || editor.readOnly.isEnabled ||
      holder.querySelector('.ce-block--selected')) return
    const field = event.target instanceof Element ? event.target.closest<HTMLElement>('[contenteditable="true"]') : null
    const blockElement = field?.closest<HTMLElement>('.ce-block')
    const selection = window.getSelection()
    if (!field || !blockElement || !selection?.isCollapsed || !selection.rangeCount) return
    const edge = selection.getRangeAt(0).cloneRange()
    if (!field.contains(edge.startContainer)) return
    const forward = event.key === 'Delete'
    if (forward) edge.setEnd(field, field.childNodes.length)
    else edge.setStart(field, 0)
    if (/[^\t\n\r ]/.test(edge.toString())) return

    const index = editor.blocks.getBlockIndex(blockElement.dataset.id!)
    const adjacentIndex = index + (forward ? 1 : -1)
    if (index < 0 || adjacentIndex < 0 || adjacentIndex >= editor.blocks.getBlocksCount()) return
    const current = editor.blocks.getBlockByIndex(index)!
    const fields = current.holder.querySelectorAll('[contenteditable="true"]')
    if (field !== fields[forward ? fields.length - 1 : 0]) return
    const adjacent = editor.blocks.getBlockByIndex(adjacentIndex)!
    const before = forward ? current : adjacent
    const after = forward ? adjacent : current
    // Preserve the following block's type and attributes even when both blocks are blank.
    let removedIndex = forward ? index : adjacentIndex
    let retained = after
    let position: 'start' | 'end' = 'start'
    if (!isBlankTextBlock(before)) {
      if (!isBlankTextBlock(after)) return
      removedIndex = forward ? adjacentIndex : index
      retained = before
      position = 'end'
    }

    event.preventDefault()
    event.stopPropagation()
    editor.blocks.delete(removedIndex)
    editor.caret.setToBlock(retained.id, position)
  }, { capture: true, signal })
}
