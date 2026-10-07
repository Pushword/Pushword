import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { API } from '@editorjs/editorjs'
import CodeBlock from './CodeBlock'

const { renderMermaid } = vi.hoisted(() => ({ renderMermaid: vi.fn() }))
vi.mock('../../../../../js-helper/src/mermaid.js', () => ({ renderMermaid }))

const tools: CodeBlock[] = []
let value: string
let listeners: (() => void)[]
const model = {}
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
    api: {
      styles: { input: 'input' },
      i18n: { t: (text: string) => text },
    } as unknown as API,
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
    expect(setModelLanguage).toHaveBeenCalledWith(model, 'plaintext')
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
