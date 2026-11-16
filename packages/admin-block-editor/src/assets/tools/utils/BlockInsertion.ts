import type EditorJS from '@editorjs/editorjs'
import { IconPlus } from '@codexteam/icons'
import './BlockInsertion.css'

export function setupBlockInsertion(
  editor: EditorJS,
  holder: HTMLElement,
  label: string,
  signal: AbortSignal,
): void {
  const redactor = holder.querySelector('.codex-editor__redactor')!
  const refresh = (): void => {
    for (let index = 0; index < editor.blocks.getBlocksCount(); index++) {
      const block = editor.blocks.getBlockByIndex(index)!
      const content = block.holder.querySelector('.ce-block__content')!
      const existing = content.querySelector(':scope > .pw-block-insertion')
      if (editor.readOnly.isEnabled || index === 0) {
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
        button.addEventListener('pointerdown', event => event.preventDefault())
        // Editor.js opens its toolbar during touchstart capture. Close it before
        // it can cover the insertion target and intercept the end of the tap.
        button.addEventListener('touchstart', () => editor.toolbar.close(), { passive: true })
        button.addEventListener('click', event => {
          event.stopPropagation()
          if (editor.readOnly.isEnabled) return
          editor.toolbar.close()
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
