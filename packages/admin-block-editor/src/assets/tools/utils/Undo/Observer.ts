/**
 * Watches the editor's DOM and reports a change once the mutations settle.
 *
 * Ported from editorjs-undo (MIT), which stopped being maintained in 2025.
 */
export class Observer {
  private readonly holder: HTMLElement
  private observer: MutationObserver | null = null
  private readonly mutationDebouncer: () => void

  constructor(registerChange: () => void, holder: HTMLElement, debounceTimer: number) {
    this.holder = holder
    this.mutationDebouncer = this.debounce(registerChange, debounceTimer)
  }

  setMutationObserver(): void {
    const target = this.holder.querySelector('.codex-editor__redactor')
    if (target === null) {
      return
    }

    this.observer = new MutationObserver((mutationList) =>
      this.mutationHandler(mutationList),
    )
    this.observer.observe(target, {
      childList: true,
      attributes: true,
      subtree: true,
      characterData: true,
      characterDataOldValue: true,
    })
  }

  private mutationHandler(mutationList: MutationRecord[]): void {
    let contentMutated = false

    for (const mutation of mutationList) {
      switch (mutation.type) {
        case 'childList':
          if (mutation.target === this.holder) {
            this.onDestroy()
          } else {
            contentMutated = true
          }
          break
        case 'characterData':
          contentMutated = true
          break
        case 'attributes':
          // A block gaining .ce-block--selected, or the table toolbox moving,
          // is not a content change.
          if (
            !(mutation.target as HTMLElement).classList?.contains('ce-block') &&
            !(mutation.target as HTMLElement).classList?.contains('tc-toolbox')
          ) {
            contentMutated = true
          }
          break
      }
    }

    if (contentMutated) {
      this.mutationDebouncer()
    }
  }

  private debounce(callback: () => void, wait: number): () => void {
    let timeout: number | undefined

    return () => {
      window.clearTimeout(timeout)
      timeout = window.setTimeout(callback, wait)
    }
  }

  private onDestroy(): void {
    document.dispatchEvent(new CustomEvent('destroy'))
    this.observer?.disconnect()
  }
}

export default Observer
