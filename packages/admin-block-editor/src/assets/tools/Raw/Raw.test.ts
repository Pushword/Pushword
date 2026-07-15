import { describe, it, expect, vi, afterEach } from 'vitest'
import Raw from './Raw'
import MonacoHelper from '../../../../../admin-monaco-editor/MonacoHelper.js'

type Listener = () => void

function makeFakeEditor(getContentHeight: () => number) {
  const listeners: { contentSize?: Listener; modelContent?: Listener } = {}
  const editor = {
    getValue: () => '',
    setValue: vi.fn(),
    getContentHeight,
    layout: vi.fn(),
    onDidContentSizeChange: (cb: Listener) => {
      listeners.contentSize = cb
    },
    onDidChangeModelContent: (cb: Listener) => {
      listeners.modelContent = cb
    },
  }
  return { editor, listeners }
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('MonacoHelper.updateHeight', () => {
  it('sizes the wrapper from getContentHeight, so wrapped lines count', () => {
    // One model line word-wrapped over 12 visual lines: getLineCount() would
    // say 1 (the old 60px bug), getContentHeight() reports the rendered 228px.
    const { editor } = makeFakeEditor(() => 228)
    const helper = new MonacoHelper(editor)
    const wrapper = document.createElement('div')

    helper.updateHeight(wrapper)

    expect(wrapper.style.height).toBe('238px')
    expect(wrapper.style.width).toBe('100%')
    expect(editor.layout).toHaveBeenCalled()
  })

  it('never goes below minHeight', () => {
    const { editor } = makeFakeEditor(() => 20)
    const helper = new MonacoHelper(editor)
    const wrapper = document.createElement('div')

    helper.updateHeight(wrapper)

    expect(wrapper.style.height).toBe('60px')
  })
})

describe('Raw monaco height wiring', () => {
  afterEach(() => {
    delete (window as any).monaco
    delete (window as any).monacoHelper
  })

  it('follows content size changes reported by Monaco (grow and shrink)', async () => {
    let contentHeight = 228
    const { editor, listeners } = makeFakeEditor(() => contentHeight)
    ;(window as any).monaco = { editor: { create: () => editor } }
    ;(window as any).monacoHelper = MonacoHelper

    const raw = new Raw({ data: { html: '<p>x</p>' }, api: {} as any, readOnly: false })
    const wrapper = raw.render()
    await flush()

    expect(wrapper.style.height).toBe('238px')

    // Rewrap after a container resize: more visual lines, same model lines
    contentHeight = 561
    listeners.contentSize!()
    expect(wrapper.style.height).toBe('571px')

    contentHeight = 40
    listeners.contentSize!()
    expect(wrapper.style.height).toBe('60px')
  })
})
