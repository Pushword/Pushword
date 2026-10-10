import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
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

describe('EditorModeManager – mode buttons', () => {
  it('hides the other mode button while one mode is open, keeping its place', () => {
    document.body.innerHTML =
      '<div id="ed" data-input-id="inp"></div><input id="inp" type="hidden">' +
      '<button data-pw-editor-mode="json"></button>' +
      '<button data-pw-editor-mode="markdown"></button>'
    const manager = new EditorModeManager('ed')
    vi.spyOn(manager as any, 'switchTo').mockImplementation(() => {})
    const button = (mode: string): HTMLElement =>
      document.querySelector(`[data-pw-editor-mode="${mode}"]`)!

    manager.toggleMarkdownEditor()
    expect(button('json').style.visibility).toBe('hidden')
    expect(button('markdown').style.visibility).toBe('')

    document.getElementById('inp')!.setAttribute('data-editor', 'markdown')
    vi.spyOn(manager as any, 'switchFrom').mockResolvedValue(undefined)
    manager.toggleMarkdownEditor()
    expect(button('json').style.visibility).toBe('')
  })
})

describe('EditorModeManager – mode buttons in JSON mode', () => {
  it('hides the markdown button while JSON is open, and restores it on leaving', () => {
    document.body.innerHTML =
      '<div id="ed" data-input-id="inp"></div><input id="inp" type="hidden">' +
      '<button data-pw-editor-mode="json"></button>' +
      '<button data-pw-editor-mode="markdown"></button>'
    const manager = new EditorModeManager('ed')
    vi.spyOn(manager as any, 'switchTo').mockImplementation(() => {})
    const button = (mode: string): HTMLElement =>
      document.querySelector(`[data-pw-editor-mode="${mode}"]`)!

    manager.toggleEditor()
    expect(button('markdown').style.visibility).toBe('hidden')
    expect(button('json').style.visibility).toBe('')

    document.getElementById('inp')!.setAttribute('data-editor', 'json')
    vi.spyOn(manager as any, 'switchFrom').mockResolvedValue(undefined)
    manager.toggleEditor()
    expect(button('markdown').style.visibility).toBe('')
  })
})

describe('EditorModeManager – opening Monaco on the mode textarea', () => {
  const mountTextarea = (): HTMLTextAreaElement => {
    document.body.innerHTML = '<textarea id="inp" data-editor="markdown"></textarea>'
    return document.querySelector('textarea')!
  }

  beforeEach(() => {
    delete (window as any).monaco
    delete (window as any).monacoHelper
    delete window.pwMonacoUrl
    delete window.pwMonacoLoading
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  // pushword/admin may already be fetching the bundle for another field on the
  // form: the markdown mode must wait for that fetch, not start a second one.
  it('waits for the Monaco fetch already in flight, then mounts Monaco on the textarea', async () => {
    const appendChild = vi
      .spyOn(document.head, 'appendChild')
      .mockImplementation((node) => node)
    let settle!: (ready: boolean) => void
    window.pwMonacoLoading = new Promise((resolve) => (settle = resolve))
    const instance = {}
    const transformTextareaToMonaco = vi.fn(() => instance)
    const textarea = mountTextarea()
    const manager = new EditorModeManager('ed')

    ;(manager as any).initMonacoEditor(textarea)
    await new Promise((resolve) => setTimeout(resolve, 0))
    ;(window as any).monaco = {}
    ;(window as any).monacoHelper = { transformTextareaToMonaco }
    settle(true)

    await vi.waitFor(() => expect(manager.getMonacoInstance()).toBe(instance))
    expect(transformTextareaToMonaco).toHaveBeenCalledWith(textarea)
    expect(appendChild).not.toHaveBeenCalled()
  })

  it('leaves the textarea as is when the Monaco bundle fails to load', async () => {
    const appendChild = vi
      .spyOn(document.head, 'appendChild')
      .mockImplementation((node) => node)
    const manager = new EditorModeManager('ed')

    ;(manager as any).initMonacoEditor(mountTextarea())
    await vi.waitFor(() => expect(appendChild).toHaveBeenCalledOnce())
    ;(appendChild.mock.calls[0]![0] as HTMLScriptElement).dispatchEvent(
      new Event('error'),
    )

    await vi.waitFor(() =>
      expect(console.error).toHaveBeenCalledWith(
        '[ERROR] Monaco helper non disponible',
        expect.anything(),
      ),
    )
    expect(manager.getMonacoInstance()).toBeNull()
    expect(window.pwMonacoLoading).toBeNull()
  })
})
