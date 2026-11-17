/** Native input stays in one editing host; history keeps the full selected range. */
export const textSelections = new WeakMap<HTMLElement, Range>()

export function textFieldAt(node: Node | null, holder: HTMLElement): HTMLElement | null {
  const element = node instanceof Element ? node : node?.parentElement
  const field = element?.closest<HTMLElement>('[contenteditable="true"]')
  return field && holder.contains(field) && field.closest('.ce-block__content') &&
    !field.closest('.monaco-editor, .editorjs-monaco-wrapper') ? field : null
}

export function textFields(holder: ParentNode): HTMLElement[] {
  return Array.from(holder.querySelectorAll<HTMLElement>('.ce-block__content [contenteditable="true"]'))
    .filter(field => !field.closest('.monaco-editor, .editorjs-monaco-wrapper') &&
      !field.querySelector('[contenteditable="true"]'))
}

/** Clone only within one editing host: Firefox cannot clone native cross-host ranges. */
export function fieldRange(field: HTMLElement, selection: Range): Range {
  const range = document.createRange()
  range.selectNodeContents(field)
  if (field.contains(selection.startContainer)) range.setStart(selection.startContainer, selection.startOffset)
  if (field.contains(selection.endContainer)) range.setEnd(selection.endContainer, selection.endOffset)
  return range
}

export function selectedBlocks(holder: HTMLElement, range: Range): HTMLElement[] {
  return Array.from(holder.querySelectorAll<HTMLElement>('.ce-block')).filter(block => range.intersectsNode(block))
}

export function wholeBlockSelected(block: HTMLElement, range: Range): boolean {
  const whole = document.createRange()
  whole.selectNode(block)
  return range.compareBoundaryPoints(Range.START_TO_START, whole) <= 0 &&
    range.compareBoundaryPoints(Range.END_TO_END, whole) >= 0
}
