// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Only the seam matters here, not EasyMDE itself: the editor writes its textarea
 * silently (forceSync assigns .value), so without these two wires the page body
 * is invisible to anything watching the form, and writing the field back leaves
 * the visible editor on the old text. The block editor exposes the same pair.
 */

const value = vi.fn()
const changeHandlers = []

/** Config handed to EasyMDE, captured at construction. */
let captured = null

vi.mock('easymde', () => ({
  default: class {
    value = value
    codemirror = { on: (event, handler) => changeHandlers.push([event, handler]) }

    constructor(config) {
      captured = config
    }
  },
}))

const { easyMDEditor } =
  await import('../../src/Resources/assets/admin.easymde-editor.js')

const fireEditorChange = () => {
  for (const [event, handler] of changeHandlers) if ('change' === event) handler()
}

describe('easyMDEditor wiring', () => {
  let textarea

  beforeEach(() => {
    value.mockClear()
    changeHandlers.length = 0
    document.body.innerHTML =
      '<form><textarea data-editor="markdown" name="Page[mainContent]"></textarea></form>'
    textarea = document.querySelector('textarea')
    easyMDEditor()
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('relays editor changes as an input event that bubbles to the form', () => {
    const seen = vi.fn()
    document.querySelector('form').addEventListener('input', seen)

    fireEditorChange()

    expect(seen).toHaveBeenCalledOnce()
  })

  it('relays every change, not just the first', () => {
    const seen = vi.fn()
    document.querySelector('form').addEventListener('input', seen)

    fireEditorChange()
    fireEditorChange()

    expect(seen).toHaveBeenCalledTimes(2)
  })

  it('exposes a write seam that goes through the editor, not the textarea', () => {
    expect(textarea.pwEditor).toBeDefined()

    textarea.pwEditor.setValue('# Recovered')

    expect(value).toHaveBeenCalledWith('# Recovered')
  })

  // Everything reading the form (recovery, the htmx Ctrl+S post, the preview)
  // reads the textarea, never CodeMirror. forceSync is what keeps the two equal,
  // so turning it off would silently ship stale content on save.
  it('keeps forceSync on, since every reader goes through the textarea', () => {
    expect(captured.forceSync).toBe(true)
  })
})
