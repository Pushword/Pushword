import { describe, it, expect, vi, beforeEach } from 'vitest'
import { EditorModeManager } from './EditorModeManager'

/**
 * Leaving the markdown mode parses Monaco's text into the blocks, then tears
 * Monaco down. The parse is asynchronous, so the teardown must wait for it:
 * a parse that fails has to leave Monaco, and the markdown in it, in place.
 */

const destroy = vi.fn()

/** A markdown-mode page: the Editor.js holder hidden, its field a textarea under Monaco. */
function markdownMode(parseMarkdown: () => Promise<void>): EditorModeManager {
  document.body.innerHTML = ''
  const holder = document.createElement('div')
  holder.id = 'ed'
  holder.setAttribute('data-input-id', 'inp')
  holder.style.display = 'none'
  const textarea = document.createElement('textarea')
  textarea.id = 'inp'
  textarea.setAttribute('data-editor', 'markdown')
  document.body.append(holder, textarea)

  ;(window as any).editors = { ed: {} }
  ;(window as any).monacoHelper = { destroy }
  ;(window as any).EditorJsParseMarkdown = class {
    parseMarkdown = parseMarkdown
  }

  const manager = new EditorModeManager('ed')
  ;(manager as any).monacoInstance = { getValue: () => '# Title' }

  return manager
}

const field = (): Element | null => document.getElementById('inp')

beforeEach(() => {
  destroy.mockClear()
  vi.spyOn(console, 'error').mockImplementation(() => {})
})

describe('EditorModeManager – leaving the markdown mode', () => {
  it('tears Monaco down only once the parse has settled', async () => {
    let settle!: () => void
    const manager = markdownMode(() => new Promise<void>((resolve) => (settle = resolve)))

    manager.toggleMarkdownEditor()
    await Promise.resolve()

    expect(destroy).not.toHaveBeenCalled()
    expect(field()?.tagName).toBe('TEXTAREA')

    settle()
    await vi.waitFor(() => expect(destroy).toHaveBeenCalledTimes(1))
    expect((field() as HTMLInputElement).type).toBe('hidden')
    expect(document.getElementById('ed')!.style.display).toBe('block')
    expect(manager.getMonacoInstance()).toBeNull()
  })

  it('leaves Monaco and the markdown in place when the parse fails', async () => {
    const manager = markdownMode(() => Promise.reject(new Error('unparsable')))

    // Called directly: through the toggle, the rejection has no one to await it.
    await expect((manager as any).switchFrom('markdown')).rejects.toThrow('unparsable')

    expect(destroy).not.toHaveBeenCalled()
    expect(field()?.tagName).toBe('TEXTAREA')
    expect(document.getElementById('ed')!.style.display).toBe('none')
    expect(manager.getMonacoInstance()).not.toBeNull()
  })
})
