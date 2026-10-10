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
