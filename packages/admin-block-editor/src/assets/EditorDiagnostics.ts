import type EditorJS from '@editorjs/editorjs'

export type EditorOperation =
  'copy' | 'history' | 'save' | 'tool' | 'uncaught' | 'promise'

interface DiagnosticConfig {
  version: string
  editorjsVersion: string
  labels: { failure: string }
}

interface Action {
  time: string
  action: string
  block: string | null
  index: number | null
}

const inputTypes = new Set([
  'insertText',
  'insertParagraph',
  'insertLineBreak',
  'insertFromPaste',
  'insertReplacementText',
  'insertCompositionText',
  'deleteCompositionText',
  'deleteContentBackward',
  'deleteContentForward',
  'deleteByCut',
  'historyUndo',
  'historyRedo',
  'formatBold',
  'formatItalic',
])
const errorNames = new Set([
  'Error',
  'TypeError',
  'RangeError',
  'SyntaxError',
  'ReferenceError',
  'NotAllowedError',
  'SecurityError',
  'NotFoundError',
  'AbortError',
])

/** A caught failure must reach the same diagnostic and user feedback as an uncaught one. */
export function reportEditorError(
  holder: Element | null,
  operation: EditorOperation,
  error: unknown,
): void {
  holder?.dispatchEvent(
    new CustomEvent('pw:editor-error', {
      bubbles: true,
      detail: { operation, error },
    }),
  )
}

/** Session-only metadata. Never read field values, clipboard payloads or exception messages. */
export class EditorDiagnostics {
  private readonly listeners = new AbortController()
  private readonly actions: Action[] = []
  private readonly errors: Array<Record<string, unknown>> = []
  private readonly panel: HTMLElement | null
  private readonly started = new Date().toISOString()

  constructor(
    private readonly holder: HTMLElement,
    private readonly editor: EditorJS,
    private readonly config: DiagnosticConfig,
  ) {
    this.panel = document.querySelector<HTMLElement>(
      `[data-pw-diagnostics-for="${holder.id}"]`,
    )
    // Reachable before any failure: a problem that throws nothing must be reportable too.
    if (this.panel) this.panel.hidden = false
    const options = { capture: true, signal: this.listeners.signal }
    for (const event of [
      'beforeinput',
      'paste',
      'copy',
      'cut',
      'compositionstart',
      'compositionend',
      'drop',
    ]) {
      holder.addEventListener(
        event,
        (event) => {
          const input = event as InputEvent
          this.record(
            event.type === 'beforeinput'
              ? `input:${inputTypes.has(input.inputType) ? input.inputType : 'other'}`
              : event.type,
            event.target,
          )
        },
        options,
      )
    }
    holder.addEventListener(
      'pw:editor-error',
      (event) => {
        const { operation, error } = (
          event as CustomEvent<{ operation: EditorOperation; error: unknown }>
        ).detail
        this.failure(operation, error)
      },
      options,
    )
    holder.addEventListener(
      'keydown',
      (event) => {
        if (
          (event.ctrlKey || event.metaKey) &&
          ['z', 'y'].includes(event.key.toLowerCase())
        ) {
          this.record(`shortcut:${event.key.toLowerCase()}`, event.target)
        }
      },
      options,
    )
    holder.parentElement?.previousElementSibling
      ?.querySelectorAll<HTMLElement>('[data-pw-editor-mode]')
      .forEach((button) => {
        button.addEventListener(
          'click',
          () => {
            this.record(
              button.dataset.pwEditorMode === 'json' ? 'mode:json' : 'mode:markdown',
            )
          },
          options,
        )
      })
    window.addEventListener(
      'error',
      (event) => {
        if (!(event instanceof ErrorEvent)) return
        // The browser defers layout notifications to another frame; no editor action failed.
        const layoutWarning =
          !event.error &&
          [
            'ResizeObserver loop completed with undelivered notifications.',
            'ResizeObserver loop limit exceeded',
          ].includes(event.message)
        this.failure(
          'uncaught',
          event.error,
          layoutWarning ? 'resize-observer-loop' : null,
        )
      },
      options,
    )
    window.addEventListener(
      'unhandledrejection',
      (event) => this.failure('promise', event.reason),
      options,
    )
    holder
      .closest('form')
      ?.addEventListener('submit', () => this.record('submit'), options)
    this.panel?.querySelector('[data-pw-download-diagnostic]')?.addEventListener(
      'click',
      () => {
        this.download()
      },
      options,
    )
    this.panel
      ?.querySelector<HTMLInputElement>('[data-pw-writing-suggestions]')
      ?.addEventListener(
        'change',
        (event) => {
          const disabled = (event.target as HTMLInputElement).checked
          holder.setAttribute('writingsuggestions', String(!disabled))
          this.record(disabled ? 'suggestions:off' : 'suggestions:on')
        },
        options,
      )
  }

  private record(action: string, target: EventTarget | null = null): void {
    const block =
      target instanceof Element ? target.closest<HTMLElement>('.ce-block') : null
    const name = block ? this.editor.blocks?.getById(block.dataset.id!)?.name : null
    this.actions.push({
      time: new Date().toISOString(),
      action,
      block: name && /^[a-zA-Z][a-zA-Z0-9_-]{0,60}$/.test(name) ? name : null,
      index: block
        ? Array.from(this.holder.querySelectorAll('.ce-block')).indexOf(block)
        : null,
    })
    if (this.actions.length > 60) this.actions.shift()
  }

  private failure(
    operation: EditorOperation,
    error: unknown,
    warning: 'resize-observer-loop' | null = null,
  ): void {
    const exception =
      error instanceof Error || error instanceof DOMException ? error : null
    const frames = Array.from(
      (exception?.stack ?? '').matchAll(
        /(admin-block-editor\.js|admin\.js|app\.js)(?:\?[^\s:)]*)?:(\d+):(\d+)/g,
      ),
      (match) => ({ asset: match[1], line: Number(match[2]), column: Number(match[3]) }),
    ).slice(0, 8)
    this.errors.push({
      time: new Date().toISOString(),
      operation,
      name: exception && errorNames.has(exception.name) ? exception.name : 'Error',
      frames,
      ...(warning ? { warning } : {}),
    })
    if (this.errors.length > 20) this.errors.shift()
    if (warning) return
    const status = this.panel?.querySelector<HTMLElement>('[data-pw-editor-error]')
    if (status) {
      status.hidden = false
      status.textContent = this.config.labels.failure
    }
  }

  report(): Record<string, unknown> {
    const script = Array.from(document.scripts).find((script) =>
      script.src.includes('/pushwordadminblockeditor/admin-block-editor.js'),
    )
    const assetVersion = script ? new URL(script.src).searchParams.get('v') : null
    return {
      schema: 1,
      started: this.started,
      generated: new Date().toISOString(),
      version: this.config.version,
      editorjs: this.config.editorjsVersion,
      assetVersion:
        assetVersion && /^[a-zA-Z0-9.-]{1,80}$/.test(assetVersion) ? assetVersion : null,
      environment: {
        userAgent: navigator.userAgent,
        language: navigator.language,
        languages: navigator.languages,
        viewport: {
          width: window.innerWidth,
          height: window.innerHeight,
          scale: window.devicePixelRatio,
        },
        secureContext: window.isSecureContext,
        clipboardWrite: Boolean(navigator.clipboard?.write),
        writingSuggestions:
          this.holder.getAttribute('writingsuggestions') ?? 'browser-default',
      },
      blocks: this.editor.blocks?.getBlocksCount() ?? null,
      actions: [...this.actions],
      errors: [...this.errors],
    }
  }

  private async download(): Promise<void> {
    const report = this.report()
    const agent = navigator as Navigator & {
      userAgentData?: { getHighEntropyValues: (keys: string[]) => Promise<unknown> }
    }
    if (agent.userAgentData) {
      report.browser = await agent.userAgentData
        .getHighEntropyValues(['fullVersionList', 'platformVersion'])
        .catch(() => null)
    }
    const url = URL.createObjectURL(
      new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' }),
    )
    const link = document.createElement('a')
    link.href = url
    link.download = 'pushword-editor-diagnostic.json'
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }

  destroy(): void {
    this.listeners.abort()
  }
}
