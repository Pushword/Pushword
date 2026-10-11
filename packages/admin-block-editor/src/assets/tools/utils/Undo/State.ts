import type EditorJS from '@editorjs/editorjs'
import type { OutputBlockData } from '@editorjs/editorjs'
import { GroupRegistry } from '../../Group/GroupRegistry'
import { embeddedEditors } from './Selection'

export type BlockState = OutputBlockData & { id: string }
export type NormalizeBlockData = (
  data: OutputBlockData['data'],
  block: Readonly<Pick<BlockState, 'id' | 'type'>>,
) => OutputBlockData['data']
export const equal = (left: unknown, right: unknown): boolean =>
  left === right || JSON.stringify(left) === JSON.stringify(right)
export const copy = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T

/** Reuse immutable snapshots of untouched tools, including pending asynchronous saves. */
export class StateCapture {
  private blocks = new Map<string, { holder: HTMLElement; state: Promise<BlockState> }>()

  constructor(
    private readonly editor: EditorJS,
    private readonly normalizeBlockData?: NormalizeBlockData,
  ) {}

  reset(): void {
    this.blocks.clear()
  }

  /** Block.save(), unlike Saver.save(), retains empty and temporarily invalid blocks. */
  capture(changed?: ReadonlySet<string>): Promise<BlockState[]> {
    const next = new Map<string, { holder: HTMLElement; state: Promise<BlockState> }>()
    const saved = Array.from(
      { length: this.editor.blocks.getBlocksCount() },
      (_, index) => {
        const block = this.editor.blocks.getBlockByIndex(index)!
        const previous = this.blocks.get(block.id)
        const state =
          changed !== undefined &&
          !changed.has(block.id) &&
          previous?.holder === block.holder
            ? previous.state
            : block.save().then((value) => {
                if (!value)
                  throw new Error(
                    `Unable to capture history for ${block.name} (${block.id})`,
                  )
                return copy({
                  id: block.id,
                  type: block.name,
                  data: this.normalizeBlockData
                    ? this.normalizeBlockData(copy(value.data), {
                        id: block.id,
                        type: block.name,
                      })
                    : value.data,
                  tunes:
                    (value as typeof value & { tunes?: OutputBlockData['tunes'] })
                      .tunes ?? {},
                })
              })
        next.set(block.id, { holder: block.holder, state })
        return state
      },
    )
    this.blocks = next
    return Promise.all(saved).catch((error: unknown) => {
      if (this.blocks === next) this.reset()
      throw error
    })
  }
}

/** Reconcile by identity against the live editor, never against filtered export indices. */
export async function applyState(
  editor: EditorJS,
  before: BlockState[],
  after: BlockState[],
): Promise<void> {
  const blocks = editor.blocks
  const previousById = new Map(before.map((block) => [block.id, block]))
  GroupRegistry.restoringHistory = true
  try {
    // Insert first so removing the last old block cannot create an unsolicited empty block.
    for (let index = 0; index < after.length; index++) {
      const next = after[index]!
      let actual =
        index < blocks.getBlocksCount() ? blocks.getBlockByIndex(index) : undefined
      let actualIndex = index
      if (actual?.id !== next.id) {
        actual = blocks.getById(next.id) ?? undefined
        actualIndex = actual ? blocks.getBlockIndex(next.id) : -1
      }
      if (actual && actual.name !== next.type) {
        blocks.insert(next.type, copy(next.data), {}, actualIndex, false, true, next.id)
        actual.call('destroy')
        actual = undefined
      } else if (!actual) {
        blocks.insert(
          next.type,
          copy(next.data),
          {},
          Math.min(index, blocks.getBlocksCount()),
          false,
          false,
          next.id,
        )
      }
      if (!actual) actualIndex = blocks.getBlockIndex(next.id)
      if (actualIndex !== index) blocks.move(index, actualIndex)
      const previous = previousById.get(next.id)
      if (!actual || !equal(previous, next)) {
        const wrapper = actual?.holder.querySelector<HTMLElement>(
          '.editorjs-monaco-wrapper',
        )
        const embedded = wrapper && embeddedEditors.get(wrapper)?.instance
        if (
          embedded &&
          previous &&
          equal({ ...previous, data: { ...previous.data, html: next.data.html } }, next)
        ) {
          embedded.setValue(next.data.html)
          continue
        }
        const data = {
          ...Object.fromEntries(
            Object.keys(previous?.data ?? {}).map((key) => [key, undefined]),
          ),
          ...copy(next.data),
        }
        const replaced = blocks.getById(next.id)!
        const view =
          replaced.holder.querySelector<HTMLInputElement>('.toggle-input')?.checked
        const updated = await blocks.update(next.id, data, copy(next.tunes ?? {}))
        const toggle = updated?.holder.querySelector<HTMLInputElement>('.toggle-input')
        if (toggle && view !== undefined && toggle.checked !== view) {
          toggle.checked = view
          toggle.dispatchEvent(new Event('change'))
        }
        // Editor.js updates replace the tool without invoking its destroy hook.
        replaced.call('destroy')
      }
    }
    const retained = new Set(after.map((block) => block.id))
    for (let index = blocks.getBlocksCount() - 1; index >= 0; index--) {
      if (!retained.has(blocks.getBlockByIndex(index)!.id)) blocks.delete(index)
    }
  } finally {
    GroupRegistry.restoringHistory = false
  }
}
