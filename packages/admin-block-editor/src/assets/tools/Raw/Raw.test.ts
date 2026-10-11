import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest'
import Raw, { type RawData } from './Raw'
import MonacoHelper from '../../../../../admin-monaco-editor/MonacoHelper.js'

type Listener = () => void

function makeFakeEditor(getContentHeight: () => number) {
  const listeners: { contentSize?: Listener; modelContent?: Listener } = {}
  const model = {
    dispose: vi.fn(),
    getPositionAt: (offset: number) => ({ lineNumber: 1, column: offset + 1 }),
  }
  const editor = {
    getModel: () => model,
    dispose: vi.fn(),
    getValue: () => '',
    setValue: vi.fn(),
    setSelection: vi.fn(),
    getContentHeight,
    layout: vi.fn(),
    onDidContentSizeChange: (cb: Listener) => {
      listeners.contentSize = cb
    },
    onDidChangeModelContent: (cb: Listener) => {
      listeners.modelContent = cb
    },
  }
  return { editor, model, listeners }
}

beforeEach(() => vi.stubGlobal('IntersectionObserver', undefined))
afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
  delete (window as any).monaco
  delete (window as any).monacoHelper
  delete window.pwMonacoUrl
  delete window.pwMonacoLoading
  document.body.replaceChildren()
})

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

describe('Raw Monaco integration', () => {


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

  it('opens Monaco on the block html, and saves html only', async () => {
    const { editor } = makeFakeEditor(() => 20)
    const create = vi.fn(() => editor)
    ;(window as any).monaco = { editor: { create } }
    ;(window as any).monacoHelper = MonacoHelper
    const raw = new Raw({
      data: { html: '<p>x</p>', stray: true },
      api: {} as any,
      readOnly: false,
    })

    expect(raw.save()).toEqual({ html: '<p>x</p>' })
    raw.render()
    await flush()

    expect(create).toHaveBeenCalledWith(
      expect.any(HTMLElement),
      expect.objectContaining({ value: '<p>x</p>', language: 'twig' }),
    )
  })

  it('saves empty html for a block inserted from the toolbox', () => {
    const raw = new Raw({ data: {} as RawData, api: {} as any, readOnly: false })

    expect(raw.save()).toEqual({ html: '' })
  })

  it('saves an intentionally emptied Monaco value once ready', async () => {
    const { editor } = makeFakeEditor(() => 20)
    ;(window as any).monaco = { editor: { create: () => editor } }
    ;(window as any).monacoHelper = MonacoHelper
    const raw = new Raw({ data: { html: '<p>x</p>' }, api: {} as any, readOnly: false })

    raw.render()
    await flush()

    expect(raw.save()).toEqual({ html: '' })
  })

  it('preserves its content when saved before Monaco is ready', () => {
    vi.spyOn(document.head, 'appendChild').mockImplementation((node) => node)
    const raw = new Raw({
      data: { html: '{{ destinations() }}' },
      api: {} as any,
      readOnly: false,
    })

    raw.render()

    expect(raw.save()).toEqual({ html: '{{ destinations() }}' })
  })

  // pushword/admin or the markdown mode may already be fetching the bundle: a
  // block mounting meanwhile must wait for that fetch, not download it again.
  it('mounts once the Monaco fetch already in flight lands, without starting another', async () => {
    const appendChild = vi
      .spyOn(document.head, 'appendChild')
      .mockImplementation((node) => node)
    let settle!: (ready: boolean) => void
    window.pwMonacoLoading = new Promise((resolve) => (settle = resolve))
    const { editor } = makeFakeEditor(() => 20)
    const create = vi.fn(() => editor)

    new Raw({ data: { html: 'x' }, api: {} as any, readOnly: false }).render()
    ;(window as any).monaco = { editor: { create } }
    ;(window as any).monacoHelper = MonacoHelper
    settle(true)
    await flush()

    expect(appendChild).not.toHaveBeenCalled()
    expect(create).toHaveBeenCalledOnce()
  })

  it('fetches the URL the dashboard published, and shares the fetch with the page', () => {
    const appendChild = vi
      .spyOn(document.head, 'appendChild')
      .mockImplementation((node) => node)
    window.pwMonacoUrl = '/bundles/pushwordadmin/monaco/app.js?v=1234'

    new Raw({ data: { html: 'x' }, api: {} as any, readOnly: false }).render()
    new Raw({ data: { html: 'y' }, api: {} as any, readOnly: false }).render()

    expect(appendChild).toHaveBeenCalledOnce()
    const script = appendChild.mock.calls[0]![0] as HTMLScriptElement
    expect(script.getAttribute('src')).toBe('/bundles/pushwordadmin/monaco/app.js?v=1234')
    expect(window.pwMonacoLoading).toBeInstanceOf(Promise)
  })
})


it('disposes its owned Monaco model when the block is removed', async () => {
  const { editor, model } = makeFakeEditor(() => 20)
  ;(window as any).monaco = { editor: { create: () => editor } }
  ;(window as any).monacoHelper = MonacoHelper
  const raw = new Raw({ data: { html: 'Code' }, api: {} as any, readOnly: false })
  raw.render()
  await flush()
  raw.destroy()
  expect(editor.dispose).toHaveBeenCalledOnce()
  expect(model.dispose).toHaveBeenCalledOnce()
  delete (window as any).monaco
  delete (window as any).monacoHelper
})

it('defers offscreen Monaco instances while preserving data and activates once near the viewport', async () => {
  let enter!: () => void
  const disconnect = vi.fn()
  vi.stubGlobal(
    'IntersectionObserver',
    class {
      constructor(callback: (entries: { isIntersecting: boolean }[]) => void) {
        enter = () => callback([{ isIntersecting: true }])
      }
      observe() {}
      disconnect = disconnect
    },
  )
  const { editor } = makeFakeEditor(() => 20)
  const create = vi.fn(() => editor)
  ;(window as any).monaco = { editor: { create } }
  ;(window as any).monacoHelper = MonacoHelper
  const raw = new Raw({
    data: { html: 'Offscreen source' },
    api: {} as any,
    readOnly: false,
  })
  const wrapper = raw.render()
  await flush()
  expect(create).not.toHaveBeenCalled()
  expect(raw.save()).toEqual({ html: 'Offscreen source' })
  expect(wrapper.querySelector('textarea')!.value).toBe('Offscreen source')
  enter()
  enter()
  await flush()
  expect(create).toHaveBeenCalledOnce()
  expect(wrapper.querySelector('textarea')).toBeNull()
  expect(disconnect).toHaveBeenCalled()
  raw.destroy()
})

it('activates a deferred editor for keyboard focus and never mounts after destruction', async () => {
  let enter!: () => void
  vi.stubGlobal(
    'IntersectionObserver',
    class {
      constructor(callback: (entries: { isIntersecting: boolean }[]) => void) {
        enter = () => callback([{ isIntersecting: true }])
      }
      observe() {}
      disconnect() {}
    },
  )
  const { editor } = makeFakeEditor(() => 20)
  const focus = vi.fn()
  const create = vi.fn(() => ({ ...editor, focus }))
  ;(window as any).monaco = { editor: { create } }
  ;(window as any).monacoHelper = MonacoHelper
  const raw = new Raw({ data: { html: 'Code' }, api: {} as any, readOnly: false })
  const wrapper = raw.render()
  document.body.append(wrapper)
  wrapper.querySelector('textarea')!.focus()
  await flush()
  expect(create).toHaveBeenCalledOnce()
  expect(focus).toHaveBeenCalledOnce()
  raw.destroy()
  wrapper.remove()
  const removed = new Raw({ data: { html: 'Removed' }, api: {} as any, readOnly: false })
  removed.render()
  removed.destroy()
  enter()
  await flush()
  expect(create).toHaveBeenCalledOnce()
})

it('keeps typing and the selected range while a deferred Monaco bundle is loading', async () => {
  let ready!: (value: boolean) => void
  window.pwMonacoLoading = new Promise((resolve) => {
    ready = resolve
  })
  const { editor } = makeFakeEditor(() => 20)
  const focus = vi.fn()
  const create = vi.fn(() => ({ ...editor, focus }))
  const raw = new Raw({ data: { html: 'Before' }, api: {} as any, readOnly: false })
  const wrapper = raw.render()
  document.body.append(wrapper)
  const preview = wrapper.querySelector('textarea')!
  preview.focus()
  preview.value = 'Before typing'
  preview.setSelectionRange(2, 7, 'backward')
  preview.dispatchEvent(new Event('input', { bubbles: true }))
  expect(raw.save()).toEqual({ html: 'Before typing' })
  ;(window as any).monaco = { editor: { create } }
  ;(window as any).monacoHelper = MonacoHelper
  ready(true)
  await flush()
  expect(create).toHaveBeenCalledWith(
    expect.any(HTMLElement),
    expect.objectContaining({ value: 'Before typing' }),
  )
  expect(editor.setSelection).toHaveBeenCalledWith({
    selectionStartLineNumber: 1,
    selectionStartColumn: 8,
    positionLineNumber: 1,
    positionColumn: 3,
  })
  expect(focus).toHaveBeenCalledOnce()
  raw.destroy()
  wrapper.remove()
  delete window.pwMonacoLoading
})
