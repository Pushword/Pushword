/** Observe content edits, including form controls whose value is not a DOM mutation. */
export default class Observer {
  private readonly observer: MutationObserver
  private timer: ReturnType<typeof setTimeout> | undefined
  private active = false
  private readonly target: Element
  private changedBlocks = new Set<string>()
  private captureAll = false

  constructor(
    private readonly changed: (blocks?: ReadonlySet<string>) => void,
    private readonly holder: HTMLElement,
  ) {
    this.target = holder.querySelector('.codex-editor__redactor')!
    this.observer = new MutationObserver((mutations) => {
      this.collect(mutations)
    })
    for (const name of ['input', 'change', 'click', 'pw:history-change']) {
      holder.addEventListener(name, this.onEvent)
    }
  }

  private mark(target: Node): boolean {
    const element = target instanceof Element ? target : target.parentElement
    const id = element?.closest<HTMLElement>('.ce-block')?.dataset.id
    if (id) this.changedBlocks.add(id)
    return Boolean(id)
  }

  private collect(mutations: MutationRecord[]): void {
    for (const mutation of mutations) {
      if (!this.isContentChange(mutation)) continue
      this.mark(mutation.target)
      this.schedule()
    }
  }

  private readonly onEvent = (event: Event): void => {
    if (!this.active) return
    // Tune controls live outside their block. Their private data can change
    // without a content mutation, so preserve a full capture at that boundary.
    if (event.target instanceof Node && !this.mark(event.target)) this.captureAll = true
    this.schedule()
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
      this.flush()
    }, 0)
  }

  flush(): void {
    this.collect(this.observer.takeRecords())
    const pending = this.timer !== undefined
    clearTimeout(this.timer)
    this.timer = undefined
    const blocks = this.captureAll ? undefined : this.changedBlocks
    this.changedBlocks = new Set()
    this.captureAll = false
    if (this.active && pending) this.changed(blocks)
  }

  pause(): void {
    this.active = false
    clearTimeout(this.timer)
    this.timer = undefined
    this.changedBlocks.clear()
    this.captureAll = false
    this.observer.disconnect()
  }

  destroy(): void {
    this.pause()
    for (const name of ['input', 'change', 'click', 'pw:history-change']) {
      this.holder.removeEventListener(name, this.onEvent)
    }
  }
}
