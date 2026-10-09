import type EditorJS from '@editorjs/editorjs'
import type { OutputBlockData, OutputData } from '@editorjs/editorjs'
import Observer from './Observer'
import { GroupRegistry } from '../../Group/GroupRegistry'
import { applyState, captureState, equal, type BlockState } from './State'
import { captureSelection, restoreSelection, type EditorSelection } from './Selection'

interface UndoOptions {
  editor: EditorJS
  config?: {
    debounceTimer?: number
    shortcuts?: { undo?: string | string[]; redo?: string | string[] }
  }
  onApply?: () => void | Promise<unknown>
  maxLength?: number
}

interface Action {
  selection: EditorSelection | null
  kind: string
  time: number
}

interface HistoryItem {
  before: BlockState[]
  after: BlockState[]
  beforeSelection: EditorSelection | null
  afterSelection: EditorSelection | null
  kind: string
  time: number
}

/**
 * An editor-wide history. Capture edits independently of typing groups, retain
 * empty blocks, and apply changes by identity without remounting untouched tools.
 */
export class Undo {
  private readonly editor: EditorJS
  private readonly holder: HTMLElement
  private readonly observer: Observer
  private readonly onApply: () => void | Promise<unknown>
  private readonly maxLength: number
  private readonly groupDelay: number
  private readonly shortcuts: { undo: string[]; redo: string[] }
  private readonly listeners = new AbortController()
  private current: BlockState[] = []
  private stack: HistoryItem[] = []
  private position = 0
  private tail: Promise<void> = Promise.resolve()
  private revision = 0
  private applying = false
  private destroyed = false
  private composing = false
  private commands = 0
  private groupClosed = true
  private lastSelection: EditorSelection | null = null
  private action: Action | null = null

  constructor({ editor, config = {}, onApply, maxLength = 100 }: UndoOptions) {
    this.editor = editor
    const { holder } = (
      editor as unknown as { configuration: { holder: string | HTMLElement } }
    ).configuration
    this.holder = typeof holder === 'string' ? document.getElementById(holder)! : holder
    this.maxLength = maxLength
    this.groupDelay = config.debounceTimer ?? 500
    this.onApply = onApply ?? (() => {})
    const defaults = { undo: ['CMD+Z'], redo: ['CMD+Y', 'CMD+SHIFT+Z'] }
    this.shortcuts = {
      undo: [config.shortcuts?.undo ?? defaults.undo].flat(),
      redo: [config.shortcuts?.redo ?? defaults.redo].flat(),
    }
    this.observer = new Observer(() => this.record(), this.holder)
    this.setEventListeners()
    void this.initialize()
  }

  static get isReadOnlySupported(): boolean {
    return true
  }

  /** Baseline the actual rendered blocks; a publication export can omit empty ones. */
  initialize(_initial?: OutputData | OutputBlockData[]): Promise<void> {
    const revision = ++this.revision
    this.observer.pause()
    GroupRegistry.flushPending()
    const state = captureState(this.editor)
    return this.enqueue(async () => {
      const blocks = await state
      if (this.destroyed || revision !== this.revision) return
      this.current = blocks
      this.stack = []
      this.position = 0
      this.action = null
      this.groupClosed = true
      this.lastSelection = captureSelection(this.holder)
      this.observer.setMutationObserver()
    })
  }

  clear(): Promise<void> {
    return this.initialize()
  }
  count(): number {
    return this.stack.length
  }
  canUndo(): boolean {
    return !this.editor.readOnly.isEnabled && this.position > 0
  }
  canRedo(): boolean {
    return !this.editor.readOnly.isEnabled && this.position < this.stack.length
  }

  /** Also used at boundaries such as saving or replacing the editor content. */
  flush(): Promise<void> {
    if (!this.applying && !this.commands) this.observer.flush()
    return this.tail
  }

  undo(): Promise<void> {
    return this.request('undo')
  }
  redo(): Promise<void> {
    return this.request('redo')
  }

  destroy(): void {
    this.destroyed = true
    this.revision++
    this.observer.destroy()
    this.listeners.abort()
  }

  private enqueue(work: () => Promise<void>): Promise<void> {
    const result = this.tail.then(work)
    this.tail = result.catch((error: unknown) => {
      console.error('Editor history failed', error)
    })
    return result
  }

  private record(): void {
    if (
      this.applying ||
      this.composing ||
      this.destroyed ||
      this.editor.readOnly.isEnabled
    )
      return
    GroupRegistry.flushPending()
    const revision = this.revision
    const automatic = this.action === null
    const action = this.action ?? {
      selection: this.lastSelection,
      kind: 'structure',
      time: performance.now(),
    }
    this.action = null
    const afterSelection = captureSelection(this.holder) ?? action.selection
    const captured = captureState(this.editor)
    void this.enqueue(async () => {
      const after = await captured
      if (revision !== this.revision || this.destroyed || equal(after, this.current))
        return
      const previous = this.stack[this.position - 1]
      const typing = [
        'insertText',
        'insertCompositionText',
        'deleteContentBackward',
        'deleteContentForward',
      ].includes(action.kind)
      const continuation =
        !this.groupClosed &&
        this.position === this.stack.length &&
        previous &&
        action.kind === previous.kind &&
        action.time - previous.time < this.groupDelay &&
        ((typing && equal(action.selection, previous.afterSelection)) ||
          (automatic && action.kind === 'structure'))
      if (continuation) {
        previous.after = after
        previous.afterSelection = afterSelection
        previous.time = action.time
      } else {
        this.stack = this.stack.slice(0, this.position)
        this.stack.push({
          before: this.current,
          after,
          beforeSelection: action.selection,
          afterSelection,
          kind: action.kind,
          time: action.time,
        })
        if (this.stack.length > this.maxLength) this.stack.shift()
        this.position = this.stack.length
      }
      this.current = after
      this.lastSelection = afterSelection
      this.groupClosed = false
    })
  }

  private request(direction: 'undo' | 'redo'): Promise<void> {
    if (this.destroyed || this.editor.readOnly.isEnabled) return Promise.resolve()
    if (!this.commands) this.observer.flush()
    this.commands++
    return this.enqueue(async () => {
      try {
        if (this.destroyed) return
        this.groupClosed = true
        if (direction === 'undo' ? !this.canUndo() : !this.canRedo()) return
        const index = direction === 'undo' ? this.position - 1 : this.position
        const item = this.stack[index]!
        const state = direction === 'undo' ? item.before : item.after
        const selection =
          direction === 'undo' ? item.beforeSelection : item.afterSelection
        this.applying = true
        this.observer.pause()
        const scroll = { x: window.scrollX, y: window.scrollY }
        await applyState(this.editor, this.current, state)
        window.scrollTo({ left: scroll.x, top: scroll.y, behavior: 'instant' })
        await restoreSelection(this.editor, this.holder, selection)
        this.current = state
        this.position += direction === 'undo' ? -1 : 1
        this.lastSelection = selection
        this.action = null
      } finally {
        this.applying = false
        this.commands--
        if (!this.destroyed) this.observer.setMutationObserver()
      }
      await this.onApply()
    })
  }

  private begin(kind: string): void {
    if (this.applying || this.commands) return
    this.observer.flush()
    this.action = {
      kind,
      time: performance.now(),
      selection: captureSelection(this.holder) ?? this.lastSelection,
    }
  }

  private matches(event: KeyboardEvent, shortcut: string): boolean {
    const parts = shortcut.toUpperCase().split('+')
    const modifier = /Mac|iPhone|iPad/.test(navigator.platform)
      ? event.metaKey
      : event.ctrlKey
    return (
      event.key.toUpperCase() === parts[parts.length - 1] &&
      modifier === parts.includes('CMD') &&
      event.shiftKey === parts.includes('SHIFT') &&
      event.altKey === parts.includes('ALT')
    )
  }

  private setEventListeners(): void {
    const options = { capture: true, signal: this.listeners.signal }
    document.addEventListener(
      'keydown',
      (event) => {
        if (event.isComposing) return
        const inside = event.target instanceof Node && this.holder.contains(event.target)
        if ((!inside && !this.commands) || this.editor.readOnly.isEnabled) return
        let direction: 'undo' | 'redo' | null = null
        if (this.shortcuts.undo.some((key) => this.matches(event, key)))
          direction = 'undo'
        else if (this.shortcuts.redo.some((key) => this.matches(event, key)))
          direction = 'redo'
        if (direction) {
          event.preventDefault()
          event.stopImmediatePropagation()
          void this.request(direction)
        } else if (inside) {
          this.begin('structure')
        }
      },
      options,
    )
    this.holder.addEventListener(
      'beforeinput',
      (event) => {
        if (this.editor.readOnly.isEnabled) return
        const input = event as InputEvent
        if (input.inputType === 'historyUndo' || input.inputType === 'historyRedo') {
          event.preventDefault()
          event.stopImmediatePropagation()
          void this.request(input.inputType === 'historyUndo' ? 'undo' : 'redo')
        } else if (!this.composing) this.begin(input.inputType)
      },
      options,
    )
    this.holder.addEventListener(
      'compositionstart',
      () => {
        this.begin('insertCompositionText')
        this.composing = true
      },
      options,
    )
    this.holder.addEventListener(
      'compositionend',
      () => {
        this.composing = false
        this.record()
      },
      options,
    )
    this.holder.addEventListener('pointerdown', () => this.begin('structure'), options)
    this.holder.addEventListener('paste', () => this.begin('insertFromPaste'), options)
    this.holder.addEventListener('cut', () => this.begin('deleteByCut'), options)
    document.addEventListener(
      'selectionchange',
      () => {
        if (!this.applying && !this.commands)
          this.lastSelection = captureSelection(this.holder) ?? this.lastSelection
      },
      { signal: this.listeners.signal },
    )
  }
}

export default Undo
