import './EditorLoading.css'

/** The server-rendered placeholder stays visible until the content has been parsed. */
export class EditorLoading {
  private readonly widget: HTMLElement | null
  private readonly status: HTMLElement | null

  constructor(private readonly holder: HTMLElement) {
    this.widget = holder.closest('[data-pw-editor-widget]')
    this.status = this.widget?.querySelector('[data-pw-editor-status]') ?? null
    this.status?.querySelector('[data-pw-editor-reload]')?.addEventListener('click', () => {
      window.location.reload()
    })
  }

  ready(): void {
    if (!this.widget || !this.status) return
    this.widget.dataset.pwEditorState = 'ready'
    this.status.hidden = true
    this.holder.removeAttribute('inert')
    this.holder.setAttribute('aria-busy', 'false')
    for (const button of this.widget.querySelectorAll<HTMLButtonElement>('.pw-editor-mode')) {
      button.disabled = false
    }
  }

  fail(): void {
    if (!this.widget || !this.status) return
    this.widget.dataset.pwEditorState = 'failed'
    this.holder.setAttribute('aria-busy', 'false')
    this.status.setAttribute('role', 'alert')
    this.status.querySelector<HTMLElement>('[data-pw-editor-loading]')!.hidden = true
    this.status.querySelector<HTMLElement>('[data-pw-editor-load-error]')!.hidden = false
    this.status.querySelector<HTMLElement>('[data-pw-editor-reload]')!.hidden = false
  }
}
