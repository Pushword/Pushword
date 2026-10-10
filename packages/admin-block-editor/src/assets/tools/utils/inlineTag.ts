import { API } from '@editorjs/editorjs'

/** Wrap what `range` holds in a new `tagName` element, left selected. */
export function wrapInTag(api: API, range: Range, tagName: string): HTMLElement {
  const wrapper = document.createElement(tagName)
  wrapper.appendChild(range.extractContents())
  range.insertNode(wrapper)
  api.selection.expandToTag(wrapper)

  return wrapper
}

/**
 * Replace `wrapper` by what it holds, left selected. Returns that selection, or
 * null when the window has none.
 */
export function unwrapTag(api: API, wrapper: HTMLElement): Selection | null {
  api.selection.expandToTag(wrapper)

  const selection = window.getSelection()
  if (!selection) return null

  const range = selection.getRangeAt(0)
  if (!range) return null

  const content = range.extractContents()
  if (!content) return null

  wrapper.parentNode?.removeChild(wrapper)
  range.insertNode(content)

  selection.removeAllRanges()
  selection.addRange(range)

  return selection
}
