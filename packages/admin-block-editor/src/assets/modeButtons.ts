/**
 * The source-mode buttons (JSON, Markdown) of an editor's widget, in the
 * container that names the editor. A site's older copy of the widget template
 * lacks that attribute: there, the buttons sit in an earlier sibling of the
 * holder's wrapper, before or after the diagnostics panel.
 */
export function modeButtonsOf(holder: HTMLElement): HTMLButtonElement[] {
  const container =
    document.querySelector(`[data-pw-editor-modes-for="${holder.id}"]`) ??
    earlierSiblingWithModeButtons(holder)

  return Array.from(
    container?.querySelectorAll<HTMLButtonElement>('[data-pw-editor-mode]') ?? [],
  )
}

function earlierSiblingWithModeButtons(holder: HTMLElement): Element | null {
  let sibling = holder.parentElement?.previousElementSibling ?? null
  while (sibling !== null && !sibling.querySelector('[data-pw-editor-mode]')) {
    sibling = sibling.previousElementSibling
  }

  return sibling
}
