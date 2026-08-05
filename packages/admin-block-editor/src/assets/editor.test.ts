import { describe, it, expect, vi, beforeEach } from 'vitest'

/**
 * The undo baseline is wired here rather than in Undo itself: an editor whose
 * content is markdown is still empty when Undo is built, so the parse that
 * follows would count as an edit and one Ctrl+Z would empty the page. Editor.js
 * and the plugins are stubbed; what is under test is when initialize() runs.
 */

const initialize = vi.fn()
const parseMarkdown = vi.fn()
const scheduleRefresh = vi.fn()

/** Config the editorJs class handed to Editor.js, captured at construction. */
let captured: any = null
/** Options the editorJs class handed to Undo, captured at construction. */
let undoOptions: any = null

vi.mock('@editorjs/editorjs', () => ({
  default: class {
    saver = { save: vi.fn(async () => ({ blocks: [] })) }
    tools = { getBlockTools: () => [] }

    constructor(config: any) {
      captured = config
    }
  },
}))
vi.mock('editorjs-drag-drop', () => ({ default: class {} }))
vi.mock('./tools/utils/Undo/Undo', () => ({
  default: class {
    initialize = initialize

    constructor(options: any) {
      undoOptions = options
    }
  },
}))
vi.mock('./outline/OutlinePanel', () => ({
  OutlinePanel: class {
    scheduleRefresh = scheduleRefresh
  },
}))
vi.mock('./tools/Hyperlink/PasteLink', () => ({ default: class {} }))
vi.mock('./tools/utils/ClipboardManager', () => ({ default: class {} }))
vi.mock('./EditorModeManager', () => ({ EditorModeManager: class {} }))

const { editorJs } = await import('./editor')

function setUpDom(): void {
  document.body.innerHTML = ''
  const holder = document.createElement('div')
  holder.id = 'ed'
  holder.setAttribute('data-input-id', 'inp')
  const input = document.createElement('input')
  input.id = 'inp'
  document.body.append(holder, input)
}

/** Run the editor bootstrap for a page whose stored content is `content`. */
function boot(content: string, extraConfig: Record<string, unknown> = {}): void {
  setUpDom()
  captured = null
  ;(window as any).editorjsConfig = { holder: 'ed', tools: {}, ...extraConfig }
  ;(window as any).pageMainContent = content
  ;(window as any).EditorJsParseMarkdown = class {
    parseMarkdown = parseMarkdown
  }

  new editorJs()
}

beforeEach(() => {
  initialize.mockClear()
  parseMarkdown.mockClear()
})

describe('editorJs – the undo baseline', () => {
  it('is taken once the parse settles, not while the editor is still empty', async () => {
    boot('# A page stored as markdown')

    captured.onReady()
    expect(parseMarkdown).toHaveBeenCalled()
    // Nothing yet: the parse lands asynchronously, so a baseline taken here
    // would hold a half-built document.
    expect(initialize).not.toHaveBeenCalled()

    await captured.onChange.call({ holder: 'ed' })

    expect(initialize).toHaveBeenCalledTimes(1)
    expect(initialize).toHaveBeenCalledWith({ blocks: [] })
  })

  it('is taken only from the first change the parse triggers', async () => {
    boot('# A page stored as markdown')
    captured.onReady()

    await captured.onChange.call({ holder: 'ed' })
    await captured.onChange.call({ holder: 'ed' })
    await captured.onChange.call({ holder: 'ed' })

    // Later edits are the user's; re-baselining on them would discard history.
    expect(initialize).toHaveBeenCalledTimes(1)
  })

  it('is left to Editor.js when the content came as JSON', async () => {
    boot('{"blocks":[{"type":"paragraph","data":{"text":"Hi"}}]}')

    captured.onReady()
    await captured.onChange.call({ holder: 'ed' })

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
    await captured.onChange.call({ holder: 'ed' })

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
  it('announces every change with an input event that bubbles', async () => {
    boot('# A page stored as markdown')
    const seen = vi.fn()
    document.addEventListener('input', seen)

    await captured.onChange.call({ holder: 'ed' })

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

  it('exposes a write seam that re-parses markdown into the blocks', () => {
    boot('# A page stored as markdown')

    const input = document.getElementById('inp')!
    expect(input.pwEditor).toBeDefined()

    input.pwEditor!.setValue('# Recovered')

    expect(parseMarkdown).toHaveBeenCalledOnce()
  })
})
