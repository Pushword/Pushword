import './admin.css'

import { editorJs } from './editor'
import { editorJsHelper } from './editorJsHelper'
import { EditorJsParseMarkdown } from './EditorJsParseMarkdown'
import { EditorJsExportMarkdown } from './EditorJsExportMarkdown'

// Export classes for global access
declare global {
  interface Window {
    EditorJsParseMarkdown: typeof EditorJsParseMarkdown
    EditorJsExportMarkdown: typeof EditorJsExportMarkdown
    editorJsHelper: editorJsHelper & { modeManagers: Record<string, any> }
    editors: Record<string, any>
  }
}

window.EditorJsParseMarkdown = EditorJsParseMarkdown
window.EditorJsExportMarkdown = EditorJsExportMarkdown
window.editorJsHelper = new editorJsHelper()

// Initialize immediately if DOM is ready, otherwise wait for load
if (document.readyState === 'loading') {
  window.addEventListener('load', function () {
    initializeEditors()
  })
} else {
  initializeEditors()
}

function initializeEditors(): void {
  const editorInstance = new editorJs()
  window.editors = editorInstance.getEditors()

  // Some workaround to get back the not functionning shortcut for editorjs create inline hyperlink
  const mainContent = document.querySelector('div[id$="_mainContent"]')
  if (mainContent) {
    mainContent.addEventListener('keydown', (event: Event) => {
      const kbEvent = event as KeyboardEvent
      if (kbEvent.ctrlKey && kbEvent.key === 'k') {
        console.log('ctrl+k')
        const el = document.querySelector(
          '[data-item-name="link"] button',
        ) as HTMLButtonElement | null
        if (el) el.click()
      }
    })
  }
}
