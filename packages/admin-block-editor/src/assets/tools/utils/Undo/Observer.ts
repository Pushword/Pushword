/** Observe content edits, including form controls whose value is not a DOM mutation. */
export default class Observer {
  private readonly observer: MutationObserver
  private timer: ReturnType<typeof setTimeout> | undefined
  private active = false
  private readonly target: Element

  constructor(
    private readonly changed: () => void,
    private readonly holder: HTMLElement,
  ) {
    this.target = holder.querySelector('.codex-editor__redactor')!
    this.observer = new MutationObserver((mutations) => {
      if (mutations.some((mutation) => this.isContentChange(mutation))) this.schedule()
    })
    for (const name of ['input', 'change', 'click', 'pw:history-change']) {
      holder.addEventListener(name, this.schedule)
    }
  }

  private isContentChange(mutation: MutationRecord): boolean {
    const element =
      mutation.target instanceof Element ? mutation.target : mutation.target.parentElement
    if (element?.closest('.monaco-editor, .tc-toolbox, .ce-toolbar, .ce-inline-toolbar'))
      return false
    if (mutation.type !== 'attributes') return true
    return (
      !element?.classList.contains('ce-block') && mutation.attributeName !== 'data-empty'
    )
  }

  setMutationObserver(): void {
    this.active = true
    this.observer.observe(this.target, {
      subtree: true,
      childList: true,
      characterData: true,
      attributes: true,
    })
  }

  private readonly schedule = (): void => {
    if (!this.active) return
    clearTimeout(this.timer)
    this.timer = setTimeout(() => {
      this.timer = undefined
      this.changed()
    }, 0)
  }

  flush(): void {
    const mutations = this.observer.takeRecords()
    const pending =
      this.timer !== undefined ||
      mutations.some((mutation) => this.isContentChange(mutation))
    clearTimeout(this.timer)
    this.timer = undefined
    if (this.active && pending) this.changed()
  }

  pause(): void {
    this.active = false
    clearTimeout(this.timer)
    this.timer = undefined
    this.observer.disconnect()
  }

  destroy(): void {
    this.pause()
    for (const name of ['input', 'change', 'click', 'pw:history-change']) {
      this.holder.removeEventListener(name, this.schedule)
    }
  }
}
