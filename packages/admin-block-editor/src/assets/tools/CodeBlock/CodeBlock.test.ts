import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { API } from '@editorjs/editorjs'
import CodeBlock, { type CodeBlockData } from './CodeBlock'

const { renderMermaid } = vi.hoisted(() => ({ renderMermaid: vi.fn() }))
vi.mock('../../../../../js-helper/src/mermaid.js', () => ({ renderMermaid }))

const tools: CodeBlock[] = []
let value: string
let listeners: (() => void)[]
const model = { dispose: vi.fn() }
const monacoEditor = {
  getValue: () => value,
  setValue: (next: string) => {
    value = next
    listeners.forEach((listener) => listener())
  },
  getModel: () => model,
  updateOptions: vi.fn(),
  dispose: vi.fn(),
  layout: vi.fn(),
  focus: vi.fn(),
  onDidContentSizeChange: vi.fn(),
  onDidChangeModelContent: (listener: () => void) => {
    listeners.push(listener)
    return { dispose: vi.fn() }
  },
}
const setModelLanguage = vi.fn()
const api = {
  styles: { input: 'input' },
  i18n: { t: (text: string) => text },
} as unknown as API

function create(
  language = 'mermaid',
  source = 'flowchart LR\n A --> B',
  readOnly = false,
) {
  value = source
  const tool = new CodeBlock({
    data: { html: source, language },
    readOnly,
    config: {
      mermaidUrl: new URL('../../../../../js-helper/src/mermaid.js', import.meta.url)
        .pathname,
    },
    api,
  })
  tools.push(tool)
  const element = tool.render()
  document.body.append(element)
  return { tool, element }
}

beforeEach(() => {
  vi.stubGlobal('IntersectionObserver', undefined)
  vi.useFakeTimers()
  vi.clearAllMocks()
  listeners = []
  renderMermaid.mockResolvedValue('<svg><text>Diagram</text></svg>')
  vi.stubGlobal('monaco', { editor: { create: () => monacoEditor, setModelLanguage } })
  vi.stubGlobal(
    'monacoHelper',
    class {
      static defaultSettings = {}
      updateHeight() {}
      autocloseTag() {}
    },
  )
})

afterEach(() => {
  tools.splice(0).forEach((tool) => tool.destroy())
  vi.unstubAllGlobals()
  vi.useRealTimers()
  document.body.innerHTML = ''
})

describe('Mermaid code block', () => {
  it('cancels a pending preview when the block is removed', async () => {
    const { tool } = create()
    tool.destroy()
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(renderMermaid).not.toHaveBeenCalled()
    expect(monacoEditor.updateOptions).not.toHaveBeenCalled()
  })

  it('offers Mermaid, previews it, and saves only the editable source', async () => {
    const { element, tool } = create()
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(element.querySelector('select')?.value).toBe('mermaid')
    expect(setModelLanguage).toHaveBeenCalledWith(model, 'mermaid')
    expect(element.querySelector('.pw-mermaid-preview')?.textContent).toBe('Diagram')
    expect(tool.save()).toEqual({ html: value, language: 'mermaid' })
  })

  it('shows only the diagram until editing, then folds without losing the source', async () => {
    const { element, tool } = create()
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    const source = element.querySelector<HTMLElement>('.pw-code-source')!
    const button = element.querySelector<HTMLButtonElement>('.pw-mermaid-edit')!
    expect(source.hidden).toBe(true)
    expect(button.textContent).toBe('Edit diagram')
    expect(button.getAttribute('aria-expanded')).toBe('false')
    expect(button.getAttribute('aria-controls')).toBe(source.id)

    button.click()
    expect(source.hidden).toBe(false)
    expect(button.textContent).toBe('Done')
    expect(button.getAttribute('aria-expanded')).toBe('true')
    expect(monacoEditor.focus).toHaveBeenCalledOnce()
    monacoEditor.setValue('flowchart LR\n A --> Edited')
    button.click()
    expect(source.hidden).toBe(true)
    expect(document.activeElement).toBe(button)
    expect(tool.save()).toEqual({ html: 'flowchart LR\n A --> Edited', language: 'mermaid' })
  })

  it('returns to reading with Escape but leaves composing input alone', async () => {
    const { element } = create()
    await vi.advanceTimersByTimeAsync(0)
    const button = element.querySelector<HTMLButtonElement>('.pw-mermaid-edit')!
    button.click()
    const code = element.querySelector<HTMLElement>('.editorjs-monaco-wrapper')!
    code.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, isComposing: true }))
    expect(button.getAttribute('aria-expanded')).toBe('true')
    const escape = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true })
    code.dispatchEvent(escape)
    expect(escape.defaultPrevented).toBe(true)
    expect(button.getAttribute('aria-expanded')).toBe('false')
    expect(document.activeElement).toBe(button)
  })

  it('opens an empty diagram for editing and keeps read-only diagrams folded', () => {
    const editable = create('mermaid', '')
    expect(editable.element.querySelector<HTMLElement>('.pw-code-source')?.hidden).toBe(false)
    const readOnly = create('mermaid', '', true)
    expect(readOnly.element.querySelector<HTMLElement>('.pw-code-source')?.hidden).toBe(true)
    expect(readOnly.element.querySelector<HTMLElement>('.pw-mermaid-edit')?.hidden).toBe(true)
  })

  it('offers fullscreen only while editing an editable Mermaid block', () => {
    const { element } = create()
    const fullscreen = element.querySelector<HTMLButtonElement>('.pw-mermaid-fullscreen')!
    expect(fullscreen.hidden).toBe(true)
    element.querySelector<HTMLButtonElement>('.pw-mermaid-edit')!.click()
    expect(fullscreen.hidden).toBe(false)
    expect(fullscreen.getAttribute('aria-label')).toBe('Enter fullscreen')
    expect(fullscreen.getAttribute('aria-pressed')).toBe('false')
    expect(create('php').element.querySelector<HTMLElement>('.pw-mermaid-fullscreen')!.hidden).toBe(true)
    expect(create('mermaid', '', true).element.querySelector<HTMLElement>('.pw-mermaid-fullscreen')!.hidden).toBe(true)
  })

  it('expands the same source and preview, then returns to editing without losing changes', async () => {
    const { element, tool } = create()
    await vi.advanceTimersByTimeAsync(0)
    element.querySelector<HTMLButtonElement>('.pw-mermaid-edit')!.click()
    const fullscreen = element.querySelector<HTMLButtonElement>('.pw-mermaid-fullscreen')!
    const dialog = element.querySelector('dialog')!
    const source = element.querySelector('.pw-code-source')!
    const preview = element.querySelector('.pw-mermaid-preview')!
    fullscreen.click()
    expect(dialog.open).toBe(true)
    expect(monacoEditor.focus).toHaveBeenCalledTimes(2)
    expect(dialog.contains(source)).toBe(true)
    expect(dialog.contains(preview)).toBe(true)
    expect(element.contains(source)).toBe(true)
    expect(fullscreen.getAttribute('aria-label')).toBe('Exit fullscreen')
    expect(fullscreen.getAttribute('aria-pressed')).toBe('true')
    monacoEditor.setValue('flowchart LR\n A --> Fullscreen')
    fullscreen.click()
    expect(dialog.open).toBe(false)
    expect(source.parentElement?.parentElement).toBe(element)
    expect(element.querySelector('.pw-mermaid-preview')).toBe(preview)
    expect(element.querySelector<HTMLElement>('.pw-code-source')!.hidden).toBe(false)
    expect(document.activeElement).toBe(fullscreen)
    expect(fullscreen.getAttribute('aria-pressed')).toBe('false')
    expect(tool.save()).toEqual({ html: 'flowchart LR\n A --> Fullscreen', language: 'mermaid' })
  })

  it('leaves fullscreen before folding on Escape and handles native dialog cancellation', () => {
    const { element } = create()
    const edit = element.querySelector<HTMLButtonElement>('.pw-mermaid-edit')!
    const fullscreen = element.querySelector<HTMLButtonElement>('.pw-mermaid-fullscreen')!
    const dialog = element.querySelector('dialog')!
    const code = element.querySelector('.editorjs-monaco-wrapper')!
    edit.click()
    fullscreen.click()
    code.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))
    expect(dialog.open).toBe(false)
    expect(edit.getAttribute('aria-expanded')).toBe('true')
    code.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))
    expect(edit.getAttribute('aria-expanded')).toBe('false')
    edit.click()
    fullscreen.click()
    const cancel = new Event('cancel', { cancelable: true })
    dialog.dispatchEvent(cancel)
    expect(cancel.defaultPrevented).toBe(true)
    expect(dialog.open).toBe(false)
    expect(edit.getAttribute('aria-expanded')).toBe('true')
  })

  it('cleans up fullscreen when finishing, changing language or destroying the block', () => {
    const { element, tool } = create()
    const edit = element.querySelector<HTMLButtonElement>('.pw-mermaid-edit')!
    const fullscreen = element.querySelector<HTMLButtonElement>('.pw-mermaid-fullscreen')!
    const dialog = element.querySelector('dialog')!
    edit.click()
    fullscreen.click()
    edit.click()
    expect(dialog.open).toBe(false)
    expect(element.querySelector<HTMLElement>('.pw-code-source')!.hidden).toBe(true)
    edit.click()
    fullscreen.click()
    const language = element.querySelector('select')!
    language.value = 'php'
    language.dispatchEvent(new Event('change'))
    expect(dialog.open).toBe(false)
    expect(fullscreen.hidden).toBe(true)
    expect(element.querySelector<HTMLElement>('.pw-code-source')!.hidden).toBe(false)
    language.value = 'mermaid'
    language.dispatchEvent(new Event('change'))
    fullscreen.click()
    expect(dialog.open).toBe(true)
    tool.destroy()
    expect(dialog.open).toBe(false)
    expect(element.querySelector('.pw-code-content')).not.toBeNull()
  })

  it('previews and saves input before Monaco has loaded', async () => {
    vi.stubGlobal('IntersectionObserver', class {
      observe() {}
      disconnect() {}
    })
    const { element, tool } = create('mermaid', '')
    const input = element.querySelector('textarea')!
    expect(input.getAttribute('aria-label')).toBe('Mermaid source')
    input.value = 'flowchart LR\n A --> Typed'
    input.dispatchEvent(new Event('input', { bubbles: true }))
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(renderMermaid).toHaveBeenCalledWith(input.value)
    expect(tool.save()).toEqual({ html: input.value, language: 'mermaid' })
  })

  it('keeps the last valid diagram when an edit has invalid syntax', async () => {
    const { element } = create()
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    const diagram = element.querySelector('.pw-mermaid-preview svg')
    element.querySelector<HTMLButtonElement>('.pw-mermaid-edit')!.click()
    renderMermaid.mockRejectedValueOnce(new Error('Parse error on line 2'))
    monacoEditor.setValue('flowchart LR\n A[')
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(element.querySelector('.pw-mermaid-preview svg')).toBe(diagram)
    expect(element.querySelector('.pw-mermaid-status')?.textContent).toBe('Last valid preview')
    const error = element.querySelector<HTMLElement>('[role="alert"]')!
    expect(error.hidden).toBe(false)
    expect(error.textContent).toContain('Parse error on line 2')

    element.querySelector<HTMLButtonElement>('.pw-mermaid-edit')!.click()
    expect(element.querySelector<HTMLElement>('.pw-code-source')?.hidden).toBe(true)
    expect(element.dataset.previewError).toBe('true')
    element.querySelector<HTMLButtonElement>('.pw-mermaid-edit')!.click()
    monacoEditor.setValue('flowchart LR\n A --> Fixed')
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(error.hidden).toBe(true)
    expect(element.dataset.previewError).toBeUndefined()
    expect(element.querySelector('.pw-mermaid-status')?.textContent).toBe('Live preview')
  })

  it('associates errors with Monaco native edit contexts and clears them on recovery', async () => {
    const { element } = create()
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    const input = document.createElement('div')
    input.setAttribute('role', 'textbox')
    element.querySelector('.editorjs-monaco-wrapper')!.append(input)
    renderMermaid.mockRejectedValueOnce(new Error('Invalid source'))
    monacoEditor.setValue('flowchart LR\n A[')
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(input.getAttribute('aria-invalid')).toBe('true')
    expect(input.getAttribute('aria-describedby')).toBe(element.querySelector('[role="alert"]')!.id)
    monacoEditor.setValue('flowchart LR\n A --> Fixed')
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(input.hasAttribute('aria-invalid')).toBe(false)
  })

  it('associates an existing error when deferred Monaco replaces the fallback input', async () => {
    vi.stubGlobal('IntersectionObserver', class {
      observe() {}
      disconnect() {}
    })
    vi.stubGlobal('monaco', { editor: {
      setModelLanguage,
      create: (container: HTMLElement) => {
        const input = document.createElement('div')
        input.setAttribute('role', 'textbox')
        container.append(input)
        return monacoEditor
      },
    } })
    renderMermaid.mockRejectedValueOnce(new Error('Invalid source'))
    const { element } = create('mermaid', 'flowchart LR\n A[')
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(element.querySelector<HTMLElement>('.pw-code-source')?.hidden).toBe(false)
    element.querySelector('textarea')!.focus()
    await vi.advanceTimersByTimeAsync(0)
    const input = element.querySelector('[role="textbox"]')!
    expect(input.getAttribute('aria-invalid')).toBe('true')
    expect(input.getAttribute('aria-describedby')).toBe(element.querySelector('[role="alert"]')!.id)
  })

  it('does not load Mermaid for other languages or empty diagrams', async () => {
    const { element } = create('php')
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(element.querySelector<HTMLElement>('.pw-mermaid-preview')?.hidden).toBe(true)
    create('mermaid', '')
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(renderMermaid).not.toHaveBeenCalled()
    expect(document.body.textContent).toContain('Enter Mermaid code')
  })

  it('debounces edits and ignores an older render finishing last', async () => {
    let finish: (svg: string) => void = () => {}
    renderMermaid.mockImplementationOnce(
      () =>
        new Promise<string>((resolve) => {
          finish = resolve
        }),
    )
    const { element } = create()
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    monacoEditor.setValue('flowchart LR\n A --> C')
    monacoEditor.setValue('flowchart LR\n A --> D')
    renderMermaid.mockResolvedValue('<svg><text>Latest</text></svg>')
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    finish('<svg><text>Old</text></svg>')
    await vi.advanceTimersByTimeAsync(0)
    expect(renderMermaid).toHaveBeenCalledTimes(2)
    expect(element.querySelector('.pw-mermaid')?.textContent).toBe('Latest')
  })

  it('ignores a preview already rendering when the block is destroyed', async () => {
    let finish: (svg: string) => void = () => {}
    renderMermaid.mockImplementationOnce(
      () => new Promise<string>((resolve) => { finish = resolve }),
    )
    const { element, tool } = create()
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(renderMermaid).toHaveBeenCalledOnce()
    const preview = element.querySelector('.pw-mermaid-preview')!
    const content = preview.innerHTML

    tool.destroy()
    finish('<svg><text>Removed diagram</text></svg>')
    await vi.advanceTimersByTimeAsync(0)

    expect(preview.innerHTML).toBe(content)
  })

  it('ignores a rejected Mermaid preview after changing language', async () => {
    let fail: (error: Error) => void = () => {}
    renderMermaid.mockImplementationOnce(
      () => new Promise<string>((_resolve, reject) => { fail = reject }),
    )
    const { element } = create()
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(renderMermaid).toHaveBeenCalledOnce()
    const language = element.querySelector('select')!
    language.value = 'php'
    language.dispatchEvent(new Event('change'))

    fail(new Error('Obsolete Mermaid error'))
    await vi.advanceTimersByTimeAsync(0)

    expect(element.querySelector<HTMLElement>('.pw-mermaid-error')!.hidden).toBe(true)
    expect(element.dataset.previewError).toBeUndefined()
    expect(element.querySelector<HTMLElement>('.pw-mermaid-preview')!.hidden).toBe(true)
    expect(element.querySelector<HTMLElement>('.pw-code-source')!.hidden).toBe(false)
  })

  it('keeps the current diagram visible until the edited preview is ready', async () => {
    const { element } = create()
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    const preview = element.querySelector('.pw-mermaid-preview')!
    const diagram = preview.querySelector('svg')
    let finish: (svg: string) => void = () => {}
    renderMermaid.mockImplementationOnce(
      () =>
        new Promise<string>((resolve) => {
          finish = resolve
        }),
    )

    monacoEditor.setValue('flowchart LR\n A --> C')
    expect(preview.querySelector('svg')).toBe(diagram)
    expect(preview.getAttribute('aria-busy')).toBe('true')
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(preview.querySelector('svg')).toBe(diagram)

    finish('<svg><text>Updated</text></svg>')
    await vi.advanceTimersByTimeAsync(0)
    expect(preview.textContent).toBe('Updated')
    expect(preview.getAttribute('aria-busy')).toBe('false')

    monacoEditor.setValue('')
    expect(preview.querySelector('svg')).toBeNull()
    expect(preview.textContent).toContain('Enter Mermaid code')
    expect(preview.getAttribute('aria-busy')).toBe('false')
  })

  it('shows syntax errors as text and recovers after a correction', async () => {
    renderMermaid.mockRejectedValueOnce(new Error('Invalid <img src=x onerror=alert(1)>'))
    const { element } = create()
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(element.textContent).toContain('Unable to render the Mermaid diagram.')
    expect(element.querySelector('img')).toBeNull()
    expect(element.querySelector('[role="status"]')?.textContent).toBe('Preview unavailable')
    expect(element.querySelector<HTMLElement>('.pw-code-source')?.hidden).toBe(false)
    monacoEditor.setValue('flowchart LR\n A --> C')
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(element.querySelector('svg')).not.toBeNull()
    expect(element.querySelector('pre')).toBeNull()
  })

  it('hides the preview when changing language and respects read-only mode', async () => {
    const { element } = create()
    const select = element.querySelector('select')!
    select.value = 'javascript'
    select.dispatchEvent(new Event('change'))
    await vi.advanceTimersByTimeAsync(300)
    await vi.dynamicImportSettled()
    expect(element.querySelector<HTMLElement>('.pw-mermaid-preview')?.hidden).toBe(true)
    expect(renderMermaid).not.toHaveBeenCalled()
    const readOnly = create('mermaid', value, true)
    await vi.advanceTimersByTimeAsync(0)
    expect(readOnly.element.querySelector('select')?.disabled).toBe(true)
    expect(monacoEditor.updateOptions).toHaveBeenCalledWith({ readOnly: true })
  })
})

describe('Code block data', () => {
  it('saves the edited code with the language picked in the select', async () => {
    const { element, tool } = create('php', 'echo 1;')
    await vi.advanceTimersByTimeAsync(0)
    const select = element.querySelector('select')!

    select.value = 'yaml'
    select.dispatchEvent(new Event('change'))
    monacoEditor.setValue('a: 1')

    expect(setModelLanguage).toHaveBeenLastCalledWith(model, 'yaml')
    expect(tool.save()).toEqual({ html: 'a: 1', language: 'yaml' })
  })

  it('defaults to html, and saves its code before Monaco is ready', () => {
    const tool = new CodeBlock({
      data: { html: '<p>x</p>' },
      readOnly: false,
      config: { mermaidUrl: '' },
      api,
    })
    tools.push(tool)
    const element = tool.render()

    expect(element.querySelector('select')?.value).toBe('html')
    expect(tool.save()).toEqual({ html: '<p>x</p>', language: 'html' })
  })

  // Undo compares block states as JSON strings, so the key order is part of the shape.
  it('saves html then language, without keys it does not own', () => {
    const savedJson = (data: Partial<CodeBlockData>) =>
      JSON.stringify(
        new CodeBlock({
          data: data as CodeBlockData,
          readOnly: false,
          config: { mermaidUrl: '' },
          api,
        }).save(),
      )

    expect(savedJson({ language: 'php', html: 'echo 1;', stray: true })).toBe(
      '{"html":"echo 1;","language":"php"}',
    )
    expect(savedJson({})).toBe('{"html":"","language":"html"}')
  })

  it('keeps a language missing from the list, and saves it', async () => {
    const { element, tool } = create('rust', 'fn main() {}')
    await vi.advanceTimersByTimeAsync(0)
    const select = element.querySelector('select')!

    expect(Array.from(select.options, (option) => option.value)).toContain('rust')
    expect(select.value).toBe('rust')
    expect(
      element.querySelector<HTMLElement>('.editorjs-monaco-wrapper')?.dataset.language,
    ).toBe('rust')
    expect(setModelLanguage).toHaveBeenCalledWith(model, 'rust')
    expect(tool.save()).toEqual({ html: 'fn main() {}', language: 'rust' })
  })

  it('opens Monaco in a language picked before it was ready', async () => {
    const { element, tool } = create('php', 'a: 1')
    const select = element.querySelector('select')!
    select.value = 'yaml'
    select.dispatchEvent(new Event('change'))

    await vi.advanceTimersByTimeAsync(0)

    expect(setModelLanguage).toHaveBeenCalledWith(model, 'yaml')
    expect(setModelLanguage).not.toHaveBeenCalledWith(model, 'php')
    expect(tool.save()).toEqual({ html: 'a: 1', language: 'yaml' })
  })
})
