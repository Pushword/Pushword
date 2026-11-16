import type EditorJS from '@editorjs/editorjs'
import { IconPlus } from '@codexteam/icons'
import './BlockInsertion.css'

// These tools expose a preview, metadata, or an editor whose Enter stays inside
// the block. Paragraphs, headings and lists already offer a normal text exit.
const needsParagraphInsertion = new Set([
  'attaches', 'card_list', 'codeBlock', 'delimiter', 'embed', 'gallery',
  'groupEnd', 'groupStart', 'image', 'notice', 'pages_list', 'quiz',
  'quote', 'raw', 'snippet', 'table',
])

export function setupBlockInsertion(
  editor: EditorJS,
  holder: HTMLElement,
  label: string,
  signal: AbortSignal,
): void {
  const redactor = holder.querySelector('.codex-editor__redactor')!
  const refresh = (): void => {
    let previousNeedsInsertion = false
    for (let index = 0; index < editor.blocks.getBlocksCount(); index++) {
      const block = editor.blocks.getBlockByIndex(index)!
      const needsInsertion = needsParagraphInsertion.has(block.name)
      const content = block.holder.querySelector('.ce-block__content')!
      const existing = content.querySelector(':scope > .pw-block-insertion')
      if (editor.readOnly.isEnabled || !previousNeedsInsertion || !needsInsertion) {
        existing?.remove()
      } else if (!existing) {
        const separator = document.createElement('div')
        separator.className = 'pw-block-insertion'
        const button = document.createElement('button')
        button.type = 'button'
        button.className = 'pw-block-insertion__button'
        button.setAttribute('aria-label', label)
        button.title = label
        button.innerHTML = `<span aria-hidden="true">${IconPlus}</span>`
        const text = document.createElement('span')
        text.className = 'pw-block-insertion__label'
        text.textContent = label
        button.append(text)
        button.addEventListener('pointerdown', event => event.preventDefault())
        button.addEventListener('click', event => {
          event.stopPropagation()
          if (editor.readOnly.isEnabled) return
          for (const selected of holder.querySelectorAll('.ce-block--selected')) {
            selected.classList.remove('ce-block--selected')
          }
          const position = editor.blocks.getBlockIndex(block.id)
          const paragraph = editor.blocks.insert('paragraph', {}, undefined, position, false)
          editor.caret.setToBlock(paragraph.id, 'start')
        })
        separator.append(button)
        content.prepend(separator)
      }
      previousNeedsInsertion = needsInsertion
    }
  }
  const observer = new MutationObserver(refresh)
  // Structural edits also cover render(), moving blocks, and undo/redo. Keeping
  // the observer outside the tools avoids watching Monaco's rendering mutations.
  observer.observe(redactor, { childList: true })
  signal.addEventListener('abort', () => {
    observer.disconnect()
    holder.querySelectorAll('.pw-block-insertion').forEach(button => button.remove())
  }, { once: true })
  refresh()
}
