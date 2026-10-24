import type EditorJS from '@editorjs/editorjs'

/** Insert first: deleting the last old block otherwise creates an extra empty one. */
export function replaceSelectedBlocks(editor: EditorJS, holder: HTMLElement, text: string): boolean {
  const indices = Array.from(holder.querySelectorAll<HTMLElement>('.ce-block--selected'),
    block => editor.blocks.getBlockIndex(block.dataset.id!))
  if (indices.length === 0) return false
  window.getSelection()?.removeAllRanges()
  const escaped = document.createElement('span')
  escaped.textContent = text
  const block = editor.blocks.insert('paragraph', { text: escaped.innerHTML }, undefined, indices[0], false)
  for (const index of indices.reverse()) editor.blocks.delete(index + 1)
  editor.caret.setToBlock(block.id, 'end')
  return true
}

export function setupBlockSelection(editor: EditorJS, holder: HTMLElement, signal: AbortSignal): void {
  const options = { capture: true, signal }
  let composingBlocks = false
  const selected = (target: EventTarget | null): boolean =>
    !editor.readOnly.isEnabled && target instanceof Element &&
    target.closest('.ce-block') !== null && holder.querySelector('.ce-block--selected') !== null
  const textRange = (target: EventTarget | null): boolean =>
    !(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) &&
    window.getSelection()?.isCollapsed === false

  holder.addEventListener('keydown', event => {
    if (!selected(event.target)) return
    // Let the browser compose, without Editor.js clearing the block highlight.
    if (event.isComposing || composingBlocks || event.key === 'Dead' || event.key === 'Process') {
      event.stopImmediatePropagation()
      return
    }
    const erases = event.key === 'Backspace' || event.key === 'Delete'
    if (event.ctrlKey || event.metaKey || event.altKey || (event.shiftKey && erases) ||
      (event.key.length !== 1 && !erases) || textRange(event.target)) return
    event.preventDefault()
    event.stopImmediatePropagation()
    replaceSelectedBlocks(editor, holder, erases ? '' : event.key)
  }, options)

  holder.addEventListener('beforeinput', event => {
    const input = event as InputEvent
    if (!selected(event.target) || composingBlocks || input.isComposing || !input.cancelable ||
      !['insertText', 'insertReplacementText'].includes(input.inputType) || input.data === null || textRange(event.target)) return
    event.preventDefault()
    event.stopImmediatePropagation()
    replaceSelectedBlocks(editor, holder, input.data)
  }, options)

  holder.addEventListener('compositionstart', event => {
    composingBlocks = selected(event.target)
  }, options)
  holder.addEventListener('compositionend', event => {
    if (!composingBlocks) return
    composingBlocks = false
    const text = (event as CompositionEvent).data
    if (text && selected(event.target)) replaceSelectedBlocks(editor, holder, text)
  }, options)

  document.addEventListener('selectionchange', () => {
    const field = document.activeElement
    const selection = window.getSelection()
    // Firefox cannot insert native text into a focused editable without a range.
    if (field instanceof HTMLElement && holder.contains(field) && field.isContentEditable &&
      selected(field) && selection && selection.rangeCount === 0) {
      const range = document.createRange()
      range.selectNodeContents(field)
      range.collapse(true)
      selection.addRange(range)
    }
  }, { signal })
}
