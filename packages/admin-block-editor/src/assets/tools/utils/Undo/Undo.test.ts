import { describe, it, expect, vi, beforeEach } from 'vitest'
import Undo from './Undo'

/**
 * The history is driven by a MutationObserver and by Editor.js itself, so the
 * suite stubs the editor and reaches the recording step directly — the same
 * white-box approach the ClipboardManager suite uses.
 */
type AnyUndo = Record<string, any>

interface StubEditor {
  editor: any
  render: ReturnType<typeof vi.fn>
  setToBlock: ReturnType<typeof vi.fn>
  /** Blocks the editor renders but save() omits, e.g. an empty paragraph */
  extraDomBlocks: number
}

function block(
  id: string,
  text: string,
): { id: string; type: string; data: { text: string } } {
  return { id, type: 'paragraph', data: { text } }
}

function newEditor(): StubEditor {
  const holder = document.createElement('div')
  holder.id = 'editorjs_test'
  const redactor = document.createElement('div')
  redactor.className = 'codex-editor__redactor'
  holder.appendChild(redactor)
  // Undo reads readOnly off the toolbox's presence
  const toolbox = document.createElement('div')
  toolbox.className = 'ce-toolbox'
  holder.appendChild(toolbox)
  document.body.appendChild(holder)

  const stub: StubEditor = {
    editor: null,
    render: vi.fn(),
    setToBlock: vi.fn(),
    extraDomBlocks: 0,
  }

  let rendered: any[] = []

  stub.editor = {
    configuration: { holder, defaultBlock: 'paragraph', readOnly: false },
    blocks: {
      render: vi.fn(async (data: { blocks: any[] }) => {
        rendered = data.blocks
        stub.render(data)
      }),
      getCurrentBlockIndex: () => 0,
      getBlockByIndex: (index: number) => ({ id: 'dom-' + index, name: 'paragraph' }),
      getBlockIndex: (id: string) => rendered.findIndex((b) => b.id === id),
      getBlocksCount: () => rendered.length + stub.extraDomBlocks,
      getById: (id: string) => rendered.find((b) => b.id === id) ?? null,
    },
    caret: { setToBlock: stub.setToBlock },
    // Editor.js hands the rendered blocks fresh ids
    saver: {
      save: vi.fn(async () => ({
        blocks: rendered.map((b, i) => ({ ...b, id: 'fresh-' + i })),
      })),
    },
  }

  return stub
}

beforeEach(() => {
  document.body.innerHTML = ''
})

describe('Undo – the baseline', () => {
  it('has nothing to undo once initialised, so the first Ctrl+Z cannot empty the page', async () => {
    const stub = newEditor()
    const undo = new Undo({ editor: stub.editor }) as unknown as AnyUndo
    const loaded = [block('a', 'first'), block('b', 'second')]

    undo.initialize({ blocks: loaded })

    expect(undo.canUndo()).toBe(false)
    await undo.undo()
    expect(stub.render).not.toHaveBeenCalled()
  })

  it('falls back to a single default block when nothing was initialised', () => {
    const stub = newEditor()
    const undo = new Undo({ editor: stub.editor }) as unknown as AnyUndo

    expect(undo.canUndo()).toBe(false)
    expect(undo.count()).toBe(0)
  })
})

describe('Undo – applying history', () => {
  it('restores whole snapshots, both back and forward', async () => {
    const stub = newEditor()
    const undo = new Undo({ editor: stub.editor }) as unknown as AnyUndo
    const before = [block('a', 'first'), block('b', 'second')]
    const after = [block('a', 'first edited'), block('b', 'second')]

    undo.initialize({ blocks: before })
    undo.save(after)

    await undo.undo()
    expect(stub.render).toHaveBeenLastCalledWith({ blocks: before })

    await undo.redo()
    expect(stub.render).toHaveBeenLastCalledWith({ blocks: after })
  })

  it('is unaffected by the editor holding blocks that save() omits', async () => {
    const stub = newEditor()
    // Two empty paragraphs the editor renders but never saves: this mismatch is
    // what used to make undo patch the wrong block.
    stub.extraDomBlocks = 2
    const undo = new Undo({ editor: stub.editor }) as unknown as AnyUndo
    const before = [block('a', 'first'), block('b', 'second')]

    undo.initialize({ blocks: before })
    undo.save([block('a', 'first edited'), block('b', 'second')])
    await undo.undo()

    expect(stub.render).toHaveBeenLastCalledWith({ blocks: before })
  })

  it('keeps the redo branch after an undo', async () => {
    const stub = newEditor()
    const undo = new Undo({ editor: stub.editor }) as unknown as AnyUndo

    undo.initialize({ blocks: [block('a', 'first')] })
    undo.save([block('a', 'edited')])
    await undo.undo()

    // The render comes back with fresh ids; if the snapshot were not re-synced
    // the next observed change would read as an edit and drop what is ahead.
    expect(undo.canRedo()).toBe(true)
  })

  it('leaves the caret in the document, where the shortcuts are bound', async () => {
    const stub = newEditor()
    const undo = new Undo({ editor: stub.editor }) as unknown as AnyUndo

    undo.initialize({ blocks: [block('a', 'first')] })
    undo.save([block('a', 'edited')])
    await undo.undo()

    expect(stub.setToBlock).toHaveBeenCalled()
  })

  it('does not record the render it performs', async () => {
    const stub = newEditor()
    const undo = new Undo({ editor: stub.editor }) as unknown as AnyUndo

    undo.initialize({ blocks: [block('a', 'first')] })
    undo.save([block('a', 'edited')])
    const depth = undo.count()

    await undo.undo()

    expect(undo.count()).toBe(depth)
  })
})

describe('Undo – shortcut parsing', () => {
  it('maps CMD to the platform modifier and lowercases the letter', () => {
    const stub = newEditor()
    const undo = new Undo({ editor: stub.editor }) as unknown as AnyUndo

    expect(undo.parseKeys(['CMD', 'Z'])).toEqual([
      /(Mac)/i.test(navigator.platform) ? 'metaKey' : 'ctrlKey',
      'z',
    ])
    expect(undo.parseKeys(['CMD', 'SHIFT', 'Z'])).toEqual([
      /(Mac)/i.test(navigator.platform) ? 'metaKey' : 'ctrlKey',
      'shiftKey',
      'z',
    ])
  })
})
