/** Only a range contained in one editable link has unambiguous link actions. */
export function selectedLink(): HTMLAnchorElement | null {
  const selection = window.getSelection()
  if (!selection?.rangeCount) return null
  const range = selection.getRangeAt(0)
  const element = range.startContainer instanceof Element
    ? range.startContainer : range.startContainer.parentElement
  const link = element?.closest<HTMLAnchorElement>('a[href]')
  if (!link || !link.contains(range.endContainer) ||
      link.closest('[contenteditable]')?.getAttribute('contenteditable') !== 'true') return null
  return link
}
