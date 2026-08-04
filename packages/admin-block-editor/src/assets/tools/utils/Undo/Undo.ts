import EditorJS, { OutputBlockData, OutputData } from '@editorjs/editorjs'
import Caret from './caret'
import Observer from './Observer'

/**
 * Undo/redo for Editor.js.
 *
 * Ported from editorjs-undo (MIT). Its maintainers put the project into passive
 * maintenance in November 2025 ("no longer receive active development or major
 * bug fixes") and Editor.js core has no undo of its own, so we own it here.
 *
 * The port keeps upstream's history stack — a snapshot of `save()` per settled
 * change — and drops the part that made redo corrupt the page: upstream applied
 * a snapshot by diffing it against the previous one and patching single blocks,
 * addressing them by position. DOM positions and positions in a saved state
 * disagree as soon as the document holds a block `save()` omits (one empty
 * paragraph is enough — Editor.js drops those), so the patches landed on the
 * wrong blocks. Here a snapshot is applied by rendering it: the states are
 * complete, so a diff has nothing to add.
 */

interface HistoryItem {
  /** Id of the block holding the caret, to put it back after the render */
  blockId: string | null
  /** Caret offset inside that block, for the tools that support it */
  caretIndex: number | null
  state: OutputBlockData[]
}

interface UndoShortcuts {
  undo?: string | string[]
  redo?: string | string[]
}

interface UndoConfig {
  debounceTimer?: number
  shortcuts?: UndoShortcuts
}

interface UndoOptions {
  editor: EditorJS
  config?: UndoConfig
  onUpdate?: () => void
  maxLength?: number
}

/** `configuration` exists on the instance but is absent from Editor.js' typings. */
interface EditorConfiguration {
  holder: string | HTMLElement
  defaultBlock: string
  readOnly: boolean
}

const DEFAULT_DEBOUNCE_TIMER = 200
const DEFAULT_MAX_LENGTH = 30
const DEFAULT_SHORTCUTS = { undo: ['CMD+Z'], redo: ['CMD+Y', 'CMD+SHIFT+Z'] }

export class Undo {
  private readonly holder: HTMLElement
  private readonly editor: EditorJS
  private readonly blocks: EditorJS['blocks']
  private readonly caret: EditorJS['caret']
  private readonly defaultBlock: string
  private readonly maxLength: number
  private readonly onUpdate: () => void
  private readonly config: {
    debounceTimer: number
    shortcuts: { undo: string[]; redo: string[] }
  }

  private readOnly: boolean
  /** Set while a snapshot is being applied, so the render is not recorded */
  private applying = false
  private stack: HistoryItem[] = []
  private position = 0
  private initialItem: HistoryItem | null = null

  constructor({ editor, config = {}, onUpdate, maxLength }: UndoOptions) {
    const { configuration } = editor as unknown as { configuration: EditorConfiguration }
    const { holder, defaultBlock } = configuration
    const shortcuts = { ...DEFAULT_SHORTCUTS, ...config.shortcuts }

    this.holder =
      typeof holder === 'string'
        ? (document.getElementById(holder) as HTMLElement)
        : holder
    this.editor = editor
    this.blocks = editor.blocks
    this.caret = editor.caret
    this.defaultBlock = defaultBlock
    this.readOnly = configuration.readOnly
    this.maxLength = maxLength ?? DEFAULT_MAX_LENGTH
    this.onUpdate = onUpdate ?? ((): void => {})
    this.config = {
      debounceTimer: config.debounceTimer ?? DEFAULT_DEBOUNCE_TIMER,
      shortcuts: {
        undo: Array.isArray(shortcuts.undo) ? shortcuts.undo : [shortcuts.undo],
        redo: Array.isArray(shortcuts.redo) ? shortcuts.redo : [shortcuts.redo],
      },
    }

    const observer = new Observer(
      () => this.registerChange(),
      this.holder,
      this.config.debounceTimer,
    )
    observer.setMutationObserver()

    this.setEventListeners()
    this.clear()
  }

  static get isReadOnlySupported(): boolean {
    return true
  }

  /** Takes the baseline from the data the editor was loaded with. */
  initialize(initialItem: OutputData | OutputBlockData[]): void {
    const state = Array.isArray(initialItem) ? initialItem : initialItem.blocks
    const firstElement: HistoryItem = { blockId: null, caretIndex: null, state }

    this.stack[0] = firstElement
    this.initialItem = firstElement
  }

  clear(): void {
    this.stack = this.initialItem
      ? [this.initialItem]
      : [
          {
            blockId: null,
            caretIndex: null,
            state: [{ type: this.defaultBlock, data: {} }],
          },
        ]
    this.position = 0
    this.onUpdate()
  }

  canUndo(): boolean {
    return !this.readOnly && this.position > 0
  }

  canRedo(): boolean {
    return !this.readOnly && this.position < this.count()
  }

  count(): number {
    return this.stack.length - 1 // -1 because of the initial item
  }

  async undo(): Promise<void> {
    if (!this.canUndo()) {
      return
    }

    this.position -= 1
    await this.applyState(this.stack[this.position]!)
  }

  async redo(): Promise<void> {
    if (!this.canRedo()) {
      return
    }

    this.position += 1
    await this.applyState(this.stack[this.position]!)
  }

  private setReadOnly(): void {
    this.readOnly = this.holder.querySelector('.ce-toolbox') === null
  }

  private registerChange(): void {
    this.setReadOnly()
    if (this.readOnly || this.applying) {
      return
    }

    void this.editor.saver.save().then((savedData) => {
      if (!this.applying && this.editorDidUpdate(savedData.blocks)) {
        this.save(savedData.blocks)
      }
    })
  }

  private editorDidUpdate(newData: OutputBlockData[]): boolean {
    const { state } = this.stack[this.position]!

    if (newData.length === 0) {
      return false
    }
    if (newData.length !== state.length) {
      return true
    }

    return JSON.stringify(state) !== JSON.stringify(newData)
  }

  private save(state: OutputBlockData[]): void {
    // Anything ahead of the current position is a redo branch the new change
    // replaces.
    this.stack = this.stack.slice(0, this.position + 1)

    const domIndex = this.blocks.getCurrentBlockIndex()
    const current = this.blocks.getBlockByIndex(domIndex)
    const caretIndex =
      current?.name === 'paragraph' || current?.name === 'header'
        ? this.getCaretIndex(domIndex)
        : null

    this.stack.push({ blockId: current?.id ?? null, caretIndex, state })

    while (this.stack.length > this.maxLength) {
      this.stack.shift()
    }
    this.position = this.stack.length - 1
    this.onUpdate()
  }

  /**
   * Renders a snapshot. Editor.js keeps the block ids carried by the state, so
   * the caret can go back to the very block it was in.
   */
  private async applyState(item: HistoryItem): Promise<void> {
    this.applying = true
    this.onUpdate()

    // A render is one block per state entry, so the caret's block keeps its
    // position even when Editor.js hands it a new id. Without a recorded block
    // — the baseline has none — we stay where the caret already is.
    const recorded =
      item.blockId === null
        ? -1
        : item.state.findIndex((block) => block.id === item.blockId)
    const caretBlockIndex = recorded >= 0 ? recorded : this.blocks.getCurrentBlockIndex()

    try {
      await this.blocks.render({ blocks: item.state })
      // The rendered blocks come back with fresh ids, so the snapshot no longer
      // matches what save() reports. Re-sync it, or the observer would read the
      // render as an edit and truncate everything past this point.
      item.state = (await this.editor.saver.save()).blocks
      this.restoreCaret(item, caretBlockIndex)
    } finally {
      this.applying = false
    }
  }

  /**
   * Puts the caret back in the document. It has to land somewhere inside the
   * holder: the undo and redo shortcuts are bound there, so an undo that left
   * the focus outside made the following redo unreachable.
   */
  private restoreCaret(item: HistoryItem, blockIndex: number): void {
    const lastIndex = this.blocks.getBlocksCount() - 1
    if (lastIndex < 0) {
      return
    }

    const domIndex = Math.max(0, Math.min(blockIndex, lastIndex))
    const caretIndex = item.caretIndex

    if (caretIndex !== null && caretIndex !== -1) {
      const target = this.blockContent(domIndex)
      if (target !== null) {
        const caret = new Caret(target)
        setTimeout(() => caret.setPos(caretIndex), 50)

        return
      }
    }

    this.caret.setToBlock(domIndex, 'end')
  }

  private blockContent(domIndex: number): HTMLElement | null {
    const content =
      this.holder.getElementsByClassName('ce-block__content')[domIndex]?.firstChild

    return content instanceof HTMLElement ? content : null
  }

  private getCaretIndex(domIndex: number): number | null {
    const target = this.blockContent(domIndex)

    return target === null ? null : new Caret(target).getPos()
  }

  private parseKeys(keys: string[]): string[] {
    const specialKeys: Record<string, string> = {
      CMD: /(Mac)/i.test(navigator.platform) ? 'metaKey' : 'ctrlKey',
      ALT: 'altKey',
      SHIFT: 'shiftKey',
    }
    const parsedKeys = keys.slice(0, -1).map((key) => specialKeys[key] ?? key)
    const last = keys[keys.length - 1] ?? ''

    parsedKeys.push(
      parsedKeys.includes('shiftKey') && keys.length === 2
        ? last.toUpperCase()
        : last.toLowerCase(),
    )

    return parsedKeys
  }

  private setEventListeners(): void {
    const { undo, redo } = this.config.shortcuts
    const parse = (shortcuts: string[]): string[][] =>
      shortcuts.map((shortcut) => this.parseKeys(shortcut.replace(/ /g, '').split('+')))

    const keysUndo = parse(undo)
    const keysRedo = parse(redo)

    const twoKeysPressed = (event: KeyboardEvent, keys: string[]): boolean =>
      keys.length === 2 &&
      Boolean(event[keys[0] as keyof KeyboardEvent]) &&
      event.key.toLowerCase() === keys[1]
    const threeKeysPressed = (event: KeyboardEvent, keys: string[]): boolean =>
      keys.length === 3 &&
      Boolean(event[keys[0] as keyof KeyboardEvent]) &&
      Boolean(event[keys[1] as keyof KeyboardEvent]) &&
      event.key.toLowerCase() === keys[2]

    const pressedKeys = (
      event: KeyboardEvent,
      keys: string[][],
      compKeys: string[][],
    ): boolean => {
      const three = keys.some((k) => threeKeysPressed(event, k))
      const two = keys.some((k) => twoKeysPressed(event, k))

      return three || (two && !compKeys.some((k) => threeKeysPressed(event, k)))
    }

    const handleUndo = (event: KeyboardEvent): void => {
      if (pressedKeys(event, keysUndo, keysRedo)) {
        event.preventDefault()
        void this.undo()
      }
    }

    const handleRedo = (event: KeyboardEvent): void => {
      if (pressedKeys(event, keysRedo, keysUndo)) {
        event.preventDefault()
        void this.redo()
      }
    }

    this.holder.addEventListener('keydown', handleUndo)
    this.holder.addEventListener('keydown', handleRedo)
    this.holder.addEventListener('destroy', () => {
      this.holder.removeEventListener('keydown', handleUndo)
      this.holder.removeEventListener('keydown', handleRedo)
    })
  }
}

export default Undo
