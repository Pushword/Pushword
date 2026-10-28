/** Keep Editor.js popovers named, searchable and inside the viewport. */
const inlineToolNames: Record<string, string> = {
  'convert-to': 'Convert to',
  bold: 'Bold',
  italic: 'Italic',
  link: 'Link',
  'inline-code': 'Code',
  marker: 'Marker',
  small: 'Small text',
  Strikethrough: 'Strikethrough',
  underline: 'Underline',
}

let started = false

function fitPopover(panel: HTMLElement): void {
  if (!panel.offsetHeight) return
  panel.style.translate = 'none'
  const rect = panel.getBoundingClientRect()
  const x = Math.max(8 - rect.left, Math.min(0, innerWidth - 8 - rect.right))
  const y = Math.max(8 - rect.top, Math.min(0, innerHeight - 8 - rect.bottom))
  panel.style.translate = `${x}px ${y}px`
}

export function setupEditorPopovers(labels: Record<string, string> = {}): void {
  if (started) return
  started = true

  // Editor.js' flipper listens on document capture. A focused form control must
  // receive its own activation/caret keys rather than activate another menu item.
  window.addEventListener(
    'keydown',
    (event) => {
      if (event.isComposing || event.ctrlKey || event.metaKey || event.altKey) return
      const control = event.target instanceof HTMLElement ? event.target : null
      if (event.key === 'Enter' && control?.matches('.link-suggestions__preview a')) {
        event.stopPropagation()
        return
      }
      if (!control?.closest('.ce-settings .ce-popover-item-html')) return
      if (
        control instanceof HTMLButtonElement &&
        (event.key === 'Enter' || event.key === ' ')
      ) {
        event.preventDefault()
        event.stopPropagation()
        control.click()
      } else if (
        control.matches('input, textarea, select, [contenteditable="true"]') &&
        ['Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)
      ) {
        event.stopPropagation()
        if (event.key === 'Enter' && control instanceof HTMLInputElement)
          event.preventDefault()
      }
    },
    true,
  )

  const refreshPopovers = (root: HTMLElement): void => {
    root
      .querySelectorAll<HTMLElement>('.ce-toolbox .ce-popover__container')
      .forEach((container) => {
        // Editor.js omits search entirely in its mobile popover implementation.
        if (
          !container.parentElement?.querySelector(':scope > .ce-popover__overlay') ||
          container.querySelector('.ce-popover__search')
        )
          return
        const search = document.createElement('div')
        search.className = 'cdx-search-field ce-popover__search'
        const input = document.createElement('input')
        input.className = 'cdx-search-field__input'
        input.placeholder = labels.Filter ?? 'Filter'
        search.append(input)
        container.prepend(search)
        input.addEventListener('input', () => {
          const query = input.value.toLocaleLowerCase()
          let matches = 0
          container.querySelectorAll<HTMLElement>('.ce-popover-item').forEach((item) => {
            const visible = (item.textContent ?? '').toLocaleLowerCase().includes(query)
            item.classList.toggle('ce-popover-item--hidden', !visible)
            if (visible) matches++
          })
          container
            .querySelector('.ce-popover__nothing-found-message')
            ?.classList.toggle(
              'ce-popover__nothing-found-message--displayed',
              matches === 0,
            )
        })
      })
    const items = [
      root,
      ...root.querySelectorAll<HTMLElement>(
        '.ce-popover-item, .ce-popover-item-html[data-item-name]',
      ),
    ]
    items.forEach((item) => {
      if (!item.matches('.ce-popover-item, .ce-popover-item-html[data-item-name]')) return
      const name = inlineToolNames[item.dataset.itemName ?? '']
      const label =
        item.querySelector('.ce-popover-item__title')?.textContent?.trim() ||
        (name && (labels[name] ?? name))
      if (!label) return
      const control = item.querySelector('button') ?? item
      control.title = label
      control.setAttribute('aria-label', label)
    })
    root
      .querySelectorAll<HTMLInputElement>('.cdx-search-field__input')
      .forEach((input) => {
        input.setAttribute('aria-label', input.placeholder)
      })
    const popovers = [root, ...root.querySelectorAll<HTMLElement>('.ce-popover--opened')]
    popovers.forEach((popover) => {
      if (!popover.matches('.ce-popover--opened')) return
      const panel = popover.querySelector<HTMLElement>(':scope > .ce-popover__container')
      if (panel) fitPopover(panel)
    })
  }

  window.addEventListener('resize', () => {
    document
      .querySelectorAll<HTMLElement>(
        '.ce-popover--opened > .ce-popover__container, .tc-popover--opened',
      )
      .forEach(fitPopover)
  })

  refreshPopovers(document.body)
  const observer = new MutationObserver((mutations) => {
    const roots = new Set<HTMLElement>()
    for (const mutation of mutations) {
      const target =
        mutation.target instanceof HTMLElement
          ? mutation.target
          : mutation.target.parentElement
      const popover = target?.closest<HTMLElement>('.ce-popover')
      if (popover) roots.add(popover)
      else
        mutation.addedNodes.forEach((node) => {
          if (node instanceof HTMLElement) roots.add(node)
        })
    }
    roots.forEach(refreshPopovers)
  })
  observer.observe(document.body, {
    childList: true,
    characterData: true,
    attributes: true,
    attributeFilter: ['class'],
    subtree: true,
  })
}
