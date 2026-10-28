import './admin.css'

import { editorJs } from './editor'
import { editorJsHelper } from './editorJsHelper'
import { EditorJsParseMarkdown } from './EditorJsParseMarkdown'
import { EditorJsExportMarkdown } from './EditorJsExportMarkdown'
import { boundInputOf } from './boundInput'

// Export classes for global access
declare global {
  interface Window {
    EditorJsParseMarkdown: typeof EditorJsParseMarkdown
    EditorJsExportMarkdown: typeof EditorJsExportMarkdown
    editorJsHelper: editorJsHelper
    editors: Record<string, any>
  }
}

window.EditorJsParseMarkdown = EditorJsParseMarkdown
window.EditorJsExportMarkdown = EditorJsExportMarkdown
window.editorJsHelper = new editorJsHelper()

// Initialize as soon as DOM is ready (don't wait for images/resources)
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () {
    initializeEditors()
  })
} else {
  initializeEditors()
}

function initializeEditors(): void {
  const editorInstance = new editorJs()
  window.editors = editorInstance.getEditors()

  document.addEventListener('ea.collection.item-added', (event) => {
    const root = (event as CustomEvent<{ newElement: HTMLElement }>).detail.newElement
    // Scripts in a collection prototype are inert until the entry joins the form.
    root.querySelectorAll<HTMLScriptElement>('script[data-pw-editorjs-config]').forEach((config) => {
      const script = document.createElement('script')
      script.textContent = config.textContent
      config.replaceWith(script)
    })
    root.querySelectorAll<HTMLElement>('.editorjs-holder').forEach((holder) => {
      editorInstance.initEditor(window.editorjsConfigs![holder.id])
    })
  })
  document.addEventListener('ea.collection.item-removed', (event) => {
    const root = (event as CustomEvent<{ deletedElement: HTMLElement }>).detail.deletedElement
    root.querySelectorAll<HTMLElement>('.editorjs-holder').forEach((holder) => {
      editorInstance.destroyEditor(holder.id, root.querySelector<HTMLTextAreaElement>(`textarea[data-editorjs="${holder.id}"]`))
      delete window.editorjsConfigs?.[holder.id]
    })
  })
  document.addEventListener('click', (event) => {
    const button = (event.target as Element).closest<HTMLButtonElement>('[data-pw-editor-mode], [data-pw-copy-markdown]')
    const widget = button?.closest<HTMLElement>('[data-pw-editor-widget]')
    if (!button || !widget) return
    const editorId = widget.dataset.pwEditorWidget!
    const manager = editorJsHelper.getModeManager(editorId)
    if (button.hasAttribute('data-pw-copy-markdown')) {
      void copyMarkdown(button, editorId)
    } else if (button.dataset.pwEditorMode === 'markdown') {
      manager?.toggleMarkdownEditor()
    } else {
      manager?.toggleEditor()
    }
  })
  document.addEventListener('keydown', (event) => {
    if (!event.ctrlKey || event.altKey || event.shiftKey || event.key.toLowerCase() !== 'u') return
    const widget = (event.target as Element).closest<HTMLElement>('[data-pw-editor-widget]')
    if (!widget) return
    event.preventDefault()
    event.stopPropagation()
    editorJsHelper.getModeManager(widget.dataset.pwEditorWidget!)?.toggleMarkdownEditor()
  }, { capture: true })
  document.body.addEventListener('keydown', (event) => {
    const target = event.target as HTMLElement
    if (!target.closest('[data-pw-editor-widget]')) return
    if (event.key === 'Backspace' && !target.matches('input, textarea') && !target.isContentEditable) {
      event.preventDefault()
    }
    // Keep document-level shortcuts from applying an inline format a second time.
    event.stopPropagation()
  })

  // Some workaround to get back the not functionning shortcut for editorjs create inline hyperlink
  const mainContent = document.querySelector('div[id$="_mainContent"]')
  if (mainContent) {
    mainContent.addEventListener('keydown', (event: Event) => {
      const kbEvent = event as KeyboardEvent
      if (kbEvent.ctrlKey && kbEvent.key === 'k') {
        const el = document.querySelector(
          '[data-item-name="link"] button',
        ) as HTMLButtonElement | null
        if (el) el.click()
      }
    })
  }
}

async function copyMarkdown(button: HTMLButtonElement, editorId: string): Promise<void> {
  const input = boundInputOf(editorId)
  const h1 = document.querySelector<HTMLInputElement>('.h1Field')?.value.trim() || ''
  const text = (h1 ? '# ' + h1 + '\n\n' : '') + (input?.value || '')
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text)
    } else {
      const helper = document.createElement('textarea')
      helper.value = text
      helper.style.position = 'fixed'
      helper.style.opacity = '0'
      document.body.appendChild(helper)
      helper.select()
      document.execCommand('copy')
      helper.remove()
    }
    const label = button.querySelector<HTMLElement>('[data-pw-copy-label]')!
    const status = button.parentElement!.querySelector<HTMLElement>('[data-pw-copy-status]')!
    label.textContent = button.dataset.copied!
    status.textContent = button.dataset.copyStatus!
    setTimeout(() => {
      label.textContent = button.dataset.copyLabel!
      status.textContent = ''
    }, 2000)
  } catch (error) {
    document.getElementById(editorId)?.dispatchEvent(new CustomEvent('pw:editor-error', {
      bubbles: true, detail: { operation: 'copy', error },
    }))
    console.error('copyPageMarkdown failed', error)
  }
}
