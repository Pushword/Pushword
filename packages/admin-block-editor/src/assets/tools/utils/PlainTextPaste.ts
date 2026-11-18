import type EditorJS from '@editorjs/editorjs'
import { replaceTextSelection } from './TextSelectionContent'
import { textSelections } from './TextSelectionState'

/** Keep Ctrl/Cmd+Shift+V literal, before Markdown and link paste handlers run. */
export function setupPlainTextPaste(editor: EditorJS, holder: HTMLElement, signal: AbortSignal): void {
  let plainTextPaste = false
  const options = { capture: true, signal }

  // ClipboardEvent has no keyboard modifiers; remember the native paste shortcut.
  window.addEventListener('keydown', (event) => {
    plainTextPaste = event.target instanceof Node && holder.contains(event.target) &&
      (event.ctrlKey || event.metaKey) && event.shiftKey && !event.altKey &&
      event.key.toLowerCase() === 'v'
  }, options)
  const reset = () => { plainTextPaste = false }
  window.addEventListener('keyup', reset, options)
  window.addEventListener('blur', reset, { signal })

  window.addEventListener('paste', (event) => {
    const requested = plainTextPaste
    reset()
    if (!requested || editor.readOnly.isEnabled || !(event.target instanceof Element) ||
        !holder.contains(event.target)) return

    const field = event.target.closest('[contenteditable]')
    if (field?.getAttribute('contenteditable') !== 'true' ||
        event.target.closest('input, textarea, .monaco-editor, .editorjs-monaco-wrapper')) return
    if (!event.clipboardData?.types.includes('text/plain')) return

    event.preventDefault()
    event.stopImmediatePropagation()
    const text = event.clipboardData.getData('text/plain')
    const range = textSelections.get(holder)
    if (range) {
      replaceTextSelection(editor, holder, range, text)
      return
    }
    // insertText replaces a native range and preserves its caret and undo behaviour.
    document.execCommand('insertText', false, text)
  }, options)
}
