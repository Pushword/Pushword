import { API } from '@editorjs/editorjs'

/**
 * Pairs groupStart/groupEnd markers by document order (stack matching, so
 * nested groups pair correctly), cascades the deletion of one marker to its
 * partner, and decorates the blocks standing between a pair.
 *
 * Pairing is recomputed after any marker is rendered, moved or removed —
 * marker order is the only thing it depends on, so ordinary block edits
 * cannot invalidate it. A marker imported from hand-written markdown with no
 * counterpart pairs with nothing: it is kept as-is and exports back unchanged.
 */
export class GroupRegistry {
  static readonly START = 'groupStart'
  static readonly END = 'groupEnd'

  private static pairs = new Map<string, string>()
  private static recomputeScheduled = false
  private static decorateScheduled = false

  /** Recompute pairing (and refresh decoration) once the current edit settles. */
  static schedule(api: API): void {
    if (GroupRegistry.recomputeScheduled) return
    GroupRegistry.recomputeScheduled = true
    setTimeout(() => {
      GroupRegistry.recomputeScheduled = false
      GroupRegistry.pairs = GroupRegistry.computePairs(GroupRegistry.markerList(api))
      GroupRegistry.decorate()
    })
  }

  /**
   * Cascade a marker deletion to its partner, from the pairing computed before
   * the removal. The deletion itself is deferred: when both markers went away
   * in one operation (multi-selection, editor clear, mode switch), the partner
   * is already gone by then and nothing happens.
   */
  static removePartnerOf(api: API, blockId: string): void {
    const partnerId = GroupRegistry.pairs.get(blockId)
    if (partnerId === undefined) return

    setTimeout(() => {
      if (api.blocks.getById(partnerId) === null) return
      api.blocks.delete(api.blocks.getBlockIndex(partnerId))
    })
  }

  /** Stack-match marker ids in document order; unmatched markers pair with nothing. */
  static computePairs(markers: { id: string; name: string }[]): Map<string, string> {
    const pairs = new Map<string, string>()
    const openStarts: string[] = []
    for (const marker of markers) {
      if (marker.name === GroupRegistry.START) {
        openStarts.push(marker.id)
      } else if (marker.name === GroupRegistry.END) {
        const startId = openStarts.pop()
        if (startId !== undefined) {
          pairs.set(startId, marker.id)
          pairs.set(marker.id, startId)
        }
      }
    }
    return pairs
  }

  private static markerList(api: API): { id: string; name: string }[] {
    const markers: { id: string; name: string }[] = []
    const count = api.blocks.getBlocksCount()
    for (let i = 0; i < count; i++) {
      const block = api.blocks.getBlockByIndex(i)
      if (
        block !== undefined &&
        (block.name === GroupRegistry.START || block.name === GroupRegistry.END)
      ) {
        markers.push({ id: block.id, name: block.name })
      }
    }
    return markers
  }

  /**
   * Refresh decoration alone — hooked on the editor's onChange, so blocks
   * dragged into or out of a group are retagged even though no marker moved.
   */
  static decorateSoon(): void {
    if (GroupRegistry.decorateScheduled) return
    GroupRegistry.decorateScheduled = true
    requestAnimationFrame(() => {
      GroupRegistry.decorateScheduled = false
      GroupRegistry.decorate()
    })
  }

  /** Tag the blocks standing between a start and its end, straight from DOM order. */
  private static decorate(): void {
    document.querySelectorAll('.codex-editor__redactor').forEach((redactor) => {
      let depth = 0
      redactor.querySelectorAll(':scope > .ce-block').forEach((block) => {
        const isStart = block.querySelector('.pw-group-start') !== null
        const isEnd = block.querySelector('.pw-group-end') !== null
        if (isEnd && depth > 0) depth--
        if (depth > 0 && !isStart) {
          block.setAttribute('data-pw-in-group', '')
        } else {
          block.removeAttribute('data-pw-in-group')
        }
        if (isStart) depth++
      })
    })
  }
}
