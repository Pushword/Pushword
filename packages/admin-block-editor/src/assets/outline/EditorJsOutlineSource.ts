import { API, BlockAPI } from '@editorjs/editorjs'
import { GroupRegistry } from '../tools/Group/GroupRegistry'
import { OutlineEntry, OutlineSource } from './OutlineModel'

/**
 * Outline source over a live EditorJS instance. Reads through the block API,
 * not saver.save(): save() drops empty paragraphs, so its indices drift from
 * the rendered blocks (the trap documented in tools/utils/Undo).
 */
export class EditorJsOutlineSource implements OutlineSource {
  constructor(private readonly api: API) {}

  entries(): OutlineEntry[] {
    const result: OutlineEntry[] = []
    const count = this.api.blocks.getBlocksCount()
    for (let index = 0; index < count; index++) {
      const block = this.api.blocks.getBlockByIndex(index)
      if (block === undefined) continue
      result.push({
        index,
        type: block.name,
        label: this.labelOf(block),
        level: this.levelOf(block),
      })
    }

    return result
  }

  navigateTo(entry: OutlineEntry): void {
    const block = this.api.blocks.getBlockByIndex(entry.index)
    if (block === undefined) return

    try {
      this.api.caret.setToBlock(entry.index, 'start')
    } catch {
      // group markers have no caret target
    }
    block.holder.scrollIntoView({ behavior: 'smooth', block: 'center' })
    block.holder.classList.add('pw-outline-flash')
    window.setTimeout(() => block.holder.classList.remove('pw-outline-flash'), 1200)
  }

  deleteSpan(start: number, end: number): void {
    // Backwards, so earlier deletions do not shift the remaining indices. A
    // span always holds both markers of any group it contains, so the
    // registry's partner cascade resolves to a no-op.
    for (let index = end; index >= start; index--) {
      this.api.blocks.delete(index)
    }
  }

  moveSpan(start: number, end: number, to: number): void {
    if (to >= start && to <= end + 1) return

    const length = end - start + 1
    if (to > end) {
      // Each move slides the next span block into position `start`.
      for (let i = 0; i < length; i++) {
        this.api.blocks.move(to - 1, start)
      }
    } else {
      for (let i = 0; i < length; i++) {
        this.api.blocks.move(to + i, start + i)
      }
    }
  }

  private levelOf(block: BlockAPI): number | null {
    if (block.name !== 'header') return null

    const heading = block.holder.querySelector('h2, h3, h4, h5, h6')
    return heading === null ? 2 : Number(heading.tagName.charAt(1))
  }

  private labelOf(block: BlockAPI): string {
    if (block.name === GroupRegistry.START) {
      const anchor =
        block.holder.querySelector<HTMLInputElement>('.pw-group-anchor')?.value ?? ''
      const cssClass =
        block.holder.querySelector<HTMLInputElement>('.pw-group-class')?.value ?? ''
      return [anchor === '' ? '' : `#${anchor}`, cssClass].filter(Boolean).join(' ')
    }
    if (block.name === GroupRegistry.END) return ''

    // Only the editable parts: the holder's full textContent would drag tool
    // chrome into the label (header level selector, table menus, code languages).
    const editable = block.holder.querySelectorAll('[contenteditable="true"]')
    const text = [...editable]
      .map((element) => element.textContent ?? '')
      .join(' ')
      .replace(/\s+/g, ' ')
      .trim()
    if (text !== '') return text

    // Raw and code blocks edit through a textarea; their first chars beat a bare type name.
    const textarea = block.holder.querySelector('textarea')
    return textarea === null ? '' : textarea.value.replace(/\s+/g, ' ').trim()
  }
}
