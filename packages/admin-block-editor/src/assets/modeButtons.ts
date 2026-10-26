/**
 * The source-mode buttons (JSON, Markdown) of an editor's widget, in the
 * container that names the editor. A site's older copy of the widget template
 * lacks that attribute: there, the buttons sit in an earlier sibling of the
 * holder's wrapper, before or after the diagnostics panel.
 */
export function modeButtonsOf(holder: HTMLElement): HTMLButtonElement[] {
  let container = document.querySelector(`[data-pw-editor-modes-for="${holder.id}"]`)
  for (
    let sibling = holder.parentElement?.previousElementSibling ?? null;
    container === null && sibling !== null;
    sibling = sibling.previousElementSibling
  ) {
    if (sibling.querySelector('[data-pw-editor-mode]')) container = sibling
  }

  return Array.from(
    container?.querySelectorAll<HTMLButtonElement>('[data-pw-editor-mode]') ?? [],
  )
}
