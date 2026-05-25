/**
 * The block settings & toolbox popovers hide their item labels via CSS
 * (`.ce-popover-item__title { display: none }`) to render as a compact icon
 * grid. Icon-only buttons hurt discoverability, so we mirror each hidden label
 * into the item's `title` attribute, giving a native tooltip on hover.
 *
 * A single observer covers every tool's popover, so tools don't have to set
 * titles individually.
 */

function applyTitles(root: ParentNode): void {
  root
    .querySelectorAll<HTMLElement>('.ce-popover-item:not([title])')
    .forEach((item) => {
      const label = item
        .querySelector('.ce-popover-item__title')
        ?.textContent?.trim()
      if (label) {
        item.title = label
      }
    })
}

let started = false

export function setupPopoverTitles(): void {
  if (started) return
  started = true

  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      mutation.addedNodes.forEach((node) => {
        if (node instanceof HTMLElement) {
          applyTitles(node)
        }
      })
    }
  })

  observer.observe(document.body, { childList: true, subtree: true })
}
