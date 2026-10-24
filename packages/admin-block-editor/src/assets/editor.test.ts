import { describe, it, expect, vi, beforeEach } from 'vitest'

/**
 * The undo baseline is wired here rather than in Undo itself: an editor whose
 * content is markdown is still empty when Undo is built, so the parse that
 * follows would count as an edit and one Ctrl+Z would empty the page. Editor.js
 * and the plugins are stubbed; what is under test is when initialize() runs.
 */

const initialize = vi.fn()
const destroyEditor = vi.fn()
const destroyUndo = vi.fn()
const parseMarkdown = vi.fn()
const scheduleRefresh = vi.fn()
const outlineCreated = vi.fn()

/** Config the editorJs class handed to Editor.js, captured at construction. */
let captured: any = null
/** Options the editorJs class handed to Undo, captured at construction. */
let undoOptions: any = null

vi.mock('@editorjs/editorjs', () => ({
  default: class {
    destroy = destroyEditor
    readOnly = { isEnabled: false }
    blocks = { getBlockIndex: () => 0, insert: vi.fn(() => ({ id: 'replacement' })), delete: vi.fn() }
    caret = { setToBlock: vi.fn() }
    saver = { save: vi.fn(async () => ({ blocks: [] })) }
    tools = {
      getBlockTools: () =>
        Object.entries<{ class: unknown }>(captured.tools).map(([name, tool]) => ({
          name,
          constructable: tool.class,
        })),
    }

    constructor(config: any) {
      captured = config
    }
  },
}))
vi.mock('editorjs-drag-drop', () => ({ default: class {} }))
vi.mock('./tools/utils/Undo/Undo', () => ({
  default: class {
    initialize = initialize
    destroy = destroyUndo

    constructor(options: any) {
      undoOptions = options
    }
  },
}))
vi.mock('./outline/OutlinePanel', () => ({
  OutlinePanel: class {
    scheduleRefresh = scheduleRefresh
    constructor() {
      outlineCreated()
    }
  },
}))
vi.mock('./tools/Hyperlink/PasteLink', () => ({ default: class {} }))
vi.mock('./tools/utils/ClipboardManager', () => ({ default: class {}, setupBlockCut: vi.fn() }))
vi.mock('./EditorModeManager', () => ({ EditorModeManager: class {} }))

const { editorJs } = await import('./editor')
const { editorJsHelper } = await import('./editorJsHelper')
const { EditorModeManager } = await import('./EditorModeManager')

function setUpDom(pageEditor = true): void {
  document.body.innerHTML = ''
  const holder = document.createElement('div')
  holder.id = 'ed'
  holder.setAttribute('data-input-id', 'inp')
  const input = document.createElement('textarea')
  input.id = 'inp'
  const form = document.createElement('form')
  form.append(holder, input)
  if (pageEditor) {
    const title = document.createElement('input')
    title.className = 'h1Field'
    form.append(title)
  }
  document.body.append(form)
}

/** Run the editor bootstrap for a page whose stored content is `content`. */
function boot(
  content: string,
  extraConfig: Record<string, unknown> = {},
  pageEditor = true,
): InstanceType<typeof editorJs> {
  setUpDom(pageEditor)
  captured = null
  ;(window as any).editorjsConfig = { holder: 'ed', tools: {}, ...extraConfig }
  ;(window as any).pageMainContent = content
  ;(window as any).EditorJsParseMarkdown = class {
    parseMarkdown = parseMarkdown
  }

  return new editorJs()
}

beforeEach(() => {
  initialize.mockClear()
  parseMarkdown.mockClear()
  destroyEditor.mockClear()
  destroyUndo.mockClear()
  outlineCreated.mockClear()
})

it('keeps the admin navigation for editors outside the page form', () => {
  boot('{"blocks":[]}', { outline: { labels: {} } }, false)
  expect(outlineCreated).not.toHaveBeenCalled()
})

it('keeps the outline available in the page editor', () => {
  boot('{"blocks":[]}', { outline: { labels: {} } })
  expect(outlineCreated).toHaveBeenCalledOnce()
})

it('accepts a site-provided tool constructor alongside the built-in tools', () => {
  class SiteTool {}
  boot('{"blocks":[]}', {
    tools: {
      siteTool: { class: SiteTool, config: { example: true } },
      paragraph: { className: 'Paragraph' },
    },
  })
  expect(captured.tools.siteTool.class).toBe(SiteTool)
  expect(captured.tools.siteTool.config).toEqual({ example: true })
  expect(captured.tools.paragraph.class).toBeTypeOf('function')
})

it.each([['x', 'x'], ['<', '&lt;'], ['&', '&amp;'], ['é', 'é']])('replaces selected blocks with one escaped character: %s', (key, escaped) => {
  const instance = boot('{"blocks":[]}')
  const holder = document.getElementById('ed')!
  holder.innerHTML = '<div class="ce-block ce-block--selected"><p contenteditable="true">Old content</p></div>'
  const field = holder.querySelector('p')!
  const selection = window.getSelection()!
  selection.collapse(field.firstChild, 0)
  const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(true)
  expect(selection.rangeCount).toBe(0)
  const editor = instance.getEditors().ed!
  expect(editor.blocks.insert).toHaveBeenCalledWith('paragraph', { text: escaped }, undefined, 0, false)
  expect(editor.blocks.delete).toHaveBeenCalledWith(1)
  expect(editor.caret.setToBlock).toHaveBeenCalledWith('replacement', 'end')
})

it('replaces several selected blocks without shifting the remaining deletion indices', () => {
  const instance = boot('{"blocks":[]}')
  const holder = document.getElementById('ed')!
  holder.innerHTML = ['Before', 'Selected one', 'Selected two', 'After'].map((text, index) =>
    `<div class="ce-block${index === 1 || index === 2 ? ' ce-block--selected' : ''}" data-id="${index}"><p contenteditable="true">${text}</p></div>`,
  ).join('')
  const field = holder.querySelectorAll('p')[1]!
  window.getSelection()!.collapse(field.firstChild, 0)
  const editor = instance.getEditors().ed!
  vi.spyOn(editor.blocks, 'getBlockIndex').mockImplementation((id) => Number(id))

  field.dispatchEvent(new KeyboardEvent('keydown', { key: 'x', bubbles: true, cancelable: true }))

  expect(editor.blocks.insert).toHaveBeenCalledWith('paragraph', { text: 'x' }, undefined, 1, false)
  expect(vi.mocked(editor.blocks.delete).mock.calls).toEqual([[3], [2]])
})

it.each(['insertText', 'insertReplacementText'])('replaces selected blocks on native input %s before a keydown', (inputType) => {
  const instance = boot('{"blocks":[]}')
  const holder = document.getElementById('ed')!
  holder.innerHTML = '<div class="ce-block ce-block--selected" data-id="0"><p contenteditable="true">Old content</p></div>'
  const field = holder.querySelector('p')!
  window.getSelection()!.collapse(field.firstChild, 0)
  const event = new InputEvent('beforeinput', { inputType, data: 'ê', bubbles: true, cancelable: true })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(true)
  expect(instance.getEditors().ed!.blocks.insert).toHaveBeenCalledWith('paragraph', { text: 'ê' }, undefined, 0, false)
})

it.each(['Delete', 'Backspace'])('erases selected blocks on %s though the browser put the caret back in one', (key) => {
  const instance = boot('{"blocks":[]}')
  const holder = document.getElementById('ed')!
  holder.innerHTML = ['Selected one', 'Selected two', 'After'].map((text, index) =>
    `<div class="ce-block${index < 2 ? ' ce-block--selected' : ''}" data-id="${index}"><p contenteditable="true">${text}</p></div>`,
  ).join('')
  const field = holder.querySelector('p')!
  window.getSelection()!.collapse(field.firstChild, 0)
  const editor = instance.getEditors().ed!
  vi.spyOn(editor.blocks, 'getBlockIndex').mockImplementation((id) => Number(id))
  const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true })

  field.dispatchEvent(event)

  expect(event.defaultPrevented).toBe(true)
  expect(editor.blocks.insert).toHaveBeenCalledWith('paragraph', { text: '' }, undefined, 0, false)
  expect(vi.mocked(editor.blocks.delete).mock.calls).toEqual([[2], [1]])
  expect(editor.caret.setToBlock).toHaveBeenCalledWith('replacement', 'end')
})

it('leaves an erasing key typed outside the blocks alone', () => {
  boot('{"blocks":[]}')
  const holder = document.getElementById('ed')!
  holder.innerHTML = '<div class="ce-block ce-block--selected"><p contenteditable="true">Text</p></div><input>'
  window.getSelection()!.collapse(holder.querySelector('p')!.firstChild, 0)
  const event = new KeyboardEvent('keydown', { key: 'Backspace', bubbles: true, cancelable: true })

  holder.querySelector('input')!.dispatchEvent(event)

  expect(event.defaultPrevented).toBe(false)
})

it.each(['input', 'button'])('keeps characters typed in a popup %s from replacing selected blocks', (tag) => {
  const instance = boot('{"blocks":[]}')
  const holder = document.getElementById('ed')!
  holder.innerHTML = `<div class="ce-block ce-block--selected"><p contenteditable="true">Text</p></div><div class="ce-settings"><${tag}></${tag}></div>`
  const control = holder.querySelector(tag)!
  const editor = instance.getEditors().ed!
  for (const key of ['x', ' ']) {
    window.getSelection()!.collapse(holder.querySelector('p')!.firstChild, 0)
    const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true })
    control.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(false)
  }
  expect(editor.blocks.insert).not.toHaveBeenCalled()
  expect(editor.blocks.delete).not.toHaveBeenCalled()
})

it.each(['read-only', 'text selection', 'unselected blocks'])('keeps native typing with %s', (state) => {
  const instance = boot('{"blocks":[]}')
  const holder = document.getElementById('ed')!
  holder.innerHTML = '<div class="ce-block ce-block--selected"><p contenteditable="true">Text</p></div>'
  const field = holder.querySelector('p')!
  const selection = window.getSelection()!
  selection.collapse(field.firstChild, 0)
  const editor = instance.getEditors().ed!
  if (state === 'read-only') editor.readOnly.isEnabled = true
  else if (state === 'text selection') selection.selectAllChildren(field)
  else field.parentElement!.classList.remove('ce-block--selected')
  const event = new KeyboardEvent('keydown', { key: 'x', bubbles: true, cancelable: true })

  field.dispatchEvent(event)

  expect(event.defaultPrevented).toBe(false)
  expect(editor.blocks.insert).not.toHaveBeenCalled()
  expect(editor.blocks.delete).not.toHaveBeenCalled()
})

it.each([
  { key: 'x', isComposing: true }, { key: 'x', ctrlKey: true },
  { key: 'x', metaKey: true }, { key: 'x', altKey: true }, { key: 'Dead' },
])('leaves native compositions and modified keys alone: %j', (options) => {
  boot('{"blocks":[]}')
  const holder = document.getElementById('ed')!
  holder.innerHTML = '<div class="ce-block ce-block--selected"><p contenteditable="true">Text</p></div>'
  const field = holder.querySelector('p')!
  const selection = window.getSelection()!
  selection.collapse(field.firstChild, 0)
  const event = new KeyboardEvent('keydown', { ...options, bubbles: true, cancelable: true })
  field.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  expect(selection.rangeCount).toBe(1)
})

it('passes the optional history normalizer to Undo without forwarding it to Editor.js', () => {
  const normalizeBlockData = (data: unknown) => data
  boot('{"blocks":[]}', { undo: { normalizeBlockData } })
  captured.onReady()
  expect(undoOptions.normalizeBlockData).toBe(normalizeBlockData)
  expect(captured.undo).toBeUndefined()
})

it('registers its mode manager where the widget mode buttons look it up', () => {
  // window.editorJsHelper is built when the bundle loads, before any editor exists.
  const helper = new editorJsHelper()
  const previous = helper.modeManagers.ed
  boot('{"blocks":[]}')
  expect(helper.modeManagers.ed).toBeInstanceOf(EditorModeManager)
  expect(helper.modeManagers.ed).not.toBe(previous)
})

describe('editorJs – the undo baseline', () => {
  it('is taken once the parse settles, not while the editor is still empty', async () => {
    boot('# A page stored as markdown')

    captured.onReady()
    expect(parseMarkdown).toHaveBeenCalled()
    // Nothing yet: the parse lands asynchronously, so a baseline taken here
    // would hold a half-built document.
    expect(initialize).not.toHaveBeenCalled()

    await captured.onChange.call(undefined)

    expect(initialize).toHaveBeenCalledTimes(1)
    expect(initialize).toHaveBeenCalledWith({ blocks: [] })
  })

  it('is taken only from the first change the parse triggers', async () => {
    boot('# A page stored as markdown')
    captured.onReady()

    await captured.onChange.call(undefined)
    await captured.onChange.call(undefined)
    await captured.onChange.call(undefined)

    // Later edits are the user's; re-baselining on them would discard history.
    expect(initialize).toHaveBeenCalledTimes(1)
  })

  it('is left to Editor.js when the content came as JSON', async () => {
    boot('{"blocks":[{"type":"paragraph","data":{"text":"Hi"}}]}')

    captured.onReady()
    await captured.onChange.call(undefined)

    // The data went to the constructor, so Editor.js baselines it itself.
    expect(parseMarkdown).not.toHaveBeenCalled()
    expect(initialize).not.toHaveBeenCalled()
  })
})

/**
 * The markdown we export is a normalisation, not the source byte for byte, so
 * the parse's write-back changes the field on a page nobody has touched. Unsaved
 * changes recovery (pushword/admin) reads the form on window load, either side
 * of that write: it needs to know one is still coming, and when it has landed.
 */
describe('editorJs – the baseline the form recovers against', () => {
  it('flags the field it is about to rewrite, and announces the rewrite', async () => {
    boot('# A page stored as markdown')
    const input = document.getElementById('inp')!
    const announced = vi.fn()
    input.addEventListener('pw:baseline-ready', announced)

    expect(input.getAttribute('data-pw-baseline-pending')).toBe('1')

    captured.onReady()
    await captured.onChange.call(undefined)

    expect(input.hasAttribute('data-pw-baseline-pending')).toBe(false)
    expect(announced).toHaveBeenCalledOnce()
  })

  it('flags nothing when the content came as JSON, which nothing rewrites', () => {
    boot('{"blocks":[{"type":"paragraph","data":{"text":"Hi"}}]}')

    expect(document.getElementById('inp')!.hasAttribute('data-pw-baseline-pending')).toBe(
      false,
    )
  })
})

/**
 * The bound field is written by assignment, which fires nothing — so the body of
 * a block-edited page was invisible to anything watching the form, and setting
 * that field back would have left the rendered blocks on the old content.
 */
describe('editorJs – the field it feeds', () => {
  it('reports a failed save and allows retrying without submitting stale content', async () => {
    const instance = boot('{"blocks":[]}')
    const input = document.getElementById('inp') as HTMLTextAreaElement
    input.value = 'last good source'
    const save = vi.mocked(instance.getEditors().ed!.saver.save)
    save.mockRejectedValueOnce(new Error('export failed'))
    const failure = vi.fn()
    document.getElementById('ed')!.addEventListener('pw:editor-error', failure)
    const submit = vi.spyOn(input.form!, 'requestSubmit').mockImplementation(() => {})
    input.form!.dispatchEvent(new SubmitEvent('submit', { cancelable: true }))
    await vi.waitFor(() => expect(failure).toHaveBeenCalledOnce())
    expect(input.value).toBe('last good source')
    expect(submit).not.toHaveBeenCalled()
    input.form!.dispatchEvent(new SubmitEvent('submit', { cancelable: true }))
    await vi.waitFor(() => expect(submit).toHaveBeenCalledOnce())
    submit.mockRestore()
  })

  it.each([true, false])(
    'flushes the latest blocks before submitting, with submitter: %s',
    async (withSubmitter) => {
      const instance = boot('```mermaid\nflowchart LR\n A --> B\n```', {
        tools: { codeBlock: { className: 'CodeBlock' } },
      })
      const save = instance.getEditors().ed!.saver.save
      vi.mocked(save).mockResolvedValue({
        blocks: [
          {
            type: 'codeBlock',
            data: { language: 'mermaid', html: 'flowchart LR\n A --> C' },
          },
        ],
      })
      const input = document.getElementById('inp')! as HTMLTextAreaElement
      input.value = 'old source'
      const form = input.form!
      const button = withSubmitter ? document.createElement('button') : undefined
      if (button) {
        button.type = 'submit'
        form.append(button)
      }
      const submitted = vi.fn((event: Event) => event.preventDefault())
      form.addEventListener('submit', submitted)
      const timer = vi.spyOn(window, 'setTimeout')
      const requestSubmit = vi
        .spyOn(form, 'requestSubmit')
        .mockImplementation((submitter) => {
          expect(save).toHaveBeenCalledOnce()
          expect(input.value).toBe('```mermaid\nflowchart LR\n A --> C\n```')
          form.dispatchEvent(
            new SubmitEvent('submit', { submitter: submitter ?? null, cancelable: true }),
          )
        })

      form.dispatchEvent(
        new SubmitEvent('submit', { submitter: button ?? null, cancelable: true }),
      )

      expect(submitted).not.toHaveBeenCalled()
      await vi.waitFor(() => expect(requestSubmit).toHaveBeenCalledOnce())
      if (button) {
        expect(requestSubmit).toHaveBeenCalledWith(button)
      } else {
        expect(requestSubmit).toHaveBeenCalledWith()
      }
      expect(submitted).toHaveBeenCalledOnce()
      expect(save).toHaveBeenCalledOnce()
      expect(timer).toHaveBeenCalledWith(expect.any(Function), 0)
      timer.mockRestore()
    },
  )

  it.each(['markdown', 'json'])(
    'keeps the active %s editor as the source on submit',
    (mode) => {
      const instance = boot('# Initial source')
      const input = document.getElementById('inp')! as HTMLTextAreaElement
      input.setAttribute('data-editor', mode)
      input.value = 'current Monaco content'
      const event = new SubmitEvent('submit', { cancelable: true })

      input.form!.dispatchEvent(event)

      expect(event.defaultPrevented).toBe(false)
      expect(instance.getEditors().ed!.saver.save).not.toHaveBeenCalled()
      expect(input.value).toBe('current Monaco content')
    },
  )

  it('announces every change with an input event that bubbles', async () => {
    boot('# A page stored as markdown')
    const seen = vi.fn()
    document.addEventListener('input', seen)

    await captured.onChange.call(undefined)

    expect(seen).toHaveBeenCalledOnce()
    document.removeEventListener('input', seen)
  })

  it('is written back after an undo, which Editor.js reports no change for', async () => {
    boot('# A page stored as markdown')
    captured.onReady()
    const seen = vi.fn()
    document.addEventListener('input', seen)

    // Undo applies a snapshot through blocks.render(), which Editor.js runs
    // with its change observer disabled: without this hook the field would keep
    // the content the undo took off the screen, and Save would store it.
    undoOptions.onApply()
    await vi.waitFor(() => expect(seen).toHaveBeenCalledOnce())

    document.removeEventListener('input', seen)
  })

  // Same silence, other reader: the panel lists the headings of a document the
  // undo has just replaced.
  it('refreshes the outline after an undo too', () => {
    boot('# A page stored as markdown', { outline: { labels: {} } })
    captured.onReady()
    scheduleRefresh.mockClear()

    undoOptions.onApply()

    expect(scheduleRefresh).toHaveBeenCalledOnce()
  })

  it('refreshes group membership after an undo too', async () => {
    const { GroupRegistry } = await import('./tools/Group/GroupRegistry')
    const decorate = vi.spyOn(GroupRegistry, 'decorateSoon').mockImplementation(() => {})
    const instance = boot('{"blocks":[]}')
    captured.onReady()
    undoOptions.onApply()
    expect(decorate).toHaveBeenCalledOnce()
    instance.getEditors().ed!.destroy()
    decorate.mockRestore()
  })

  it('exposes a write seam that re-parses markdown into the blocks', () => {
    boot('# A page stored as markdown')

    const input = document.getElementById('inp')!
    expect(input.pwEditor).toBeDefined()

    input.pwEditor!.setValue('# Recovered')

    expect(parseMarkdown).toHaveBeenCalledOnce()
  })
})

it('destroys history listeners with the editor', () => {
  const app = boot('{"blocks":[]}')
  captured.onReady()
  app.getEditors().ed!.destroy()
  expect(destroyUndo).toHaveBeenCalledOnce()
  expect(destroyEditor).toHaveBeenCalledOnce()
})

it('does not overwrite a newer undo export with an older asynchronous save', async () => {
  const instance = boot('{"blocks":[]}', {
    tools: { codeBlock: { className: 'CodeBlock' } },
  })
  const save = vi.mocked(instance.getEditors().ed!.saver.save)
  let complete!: (value: any) => void
  save.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        complete = resolve
      }),
  )
  save.mockResolvedValueOnce({
    blocks: [{ type: 'codeBlock', data: { html: 'current', language: 'javascript' } }],
  })
  const old = instance.editorjsSave('ed')
  await instance.editorjsSave('ed')
  complete({
    blocks: [{ type: 'codeBlock', data: { html: 'stale', language: 'javascript' } }],
  })
  expect((await old)?.blocks[0]?.data.html).toBe('current')
  expect((document.getElementById('inp') as HTMLTextAreaElement).value).toContain(
    'current',
  )
})

it('coalesces markdown exports after a burst of history commands', async () => {
  vi.useFakeTimers()
  try {
    const instance = boot('{"blocks":[]}')
    captured.onReady()
    const save = instance.getEditors().ed!.saver.save
    undoOptions.onApply()
    undoOptions.onApply()
    undoOptions.onApply()
    expect(save).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(150)
    expect(save).toHaveBeenCalledOnce()
  } finally {
    vi.useRealTimers()
  }
})

it('decorates groups for structural changes but not ordinary typing', async () => {
  const { GroupRegistry } = await import('./tools/Group/GroupRegistry')
  const decorate = vi.spyOn(GroupRegistry, 'decorateSoon').mockImplementation(() => {})
  boot('{"blocks":[]}')
  await captured.onChange.call({ holder: 'ed' }, {}, new CustomEvent('block-changed'))
  expect(decorate).not.toHaveBeenCalled()
  await captured.onChange.call({ holder: 'ed' }, {}, [
    new CustomEvent('block-changed'),
    new CustomEvent('block-moved'),
  ])
  expect(decorate).toHaveBeenCalledOnce()
  decorate.mockRestore()
})
