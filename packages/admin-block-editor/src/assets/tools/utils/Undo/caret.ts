/**
 * Caret offset inside a contenteditable, counted in characters rather than in
 * (node, offset) pairs, so it survives the re-render an undo performs.
 *
 * Ported from vanilla-caret-js (MIT), which came in with editorjs-undo.
 */
export class Caret {
  private readonly target: HTMLElement | HTMLTextAreaElement

  constructor(target: HTMLElement | HTMLTextAreaElement) {
    this.target = target
  }

  private get isContentEditable(): boolean {
    return (this.target as HTMLElement).contentEditable === 'true'
  }

  /** Character offset of the caret, or -1 when the target is not focused. */
  getPos(): number {
    if (document.activeElement !== this.target) {
      return -1
    }

    if (!this.isContentEditable) {
      return (this.target as HTMLTextAreaElement).selectionStart ?? -1
    }

    const selection = document.getSelection()
    if (selection === null || selection.rangeCount === 0) {
      return -1
    }

    const range = selection.getRangeAt(0).cloneRange()
    range.selectNodeContents(this.target)
    range.setEnd(selection.getRangeAt(0).endContainer, selection.getRangeAt(0).endOffset)

    return range.toString().length
  }

  setPos(position: number): void {
    if (position < 0) {
      return
    }

    if (!this.isContentEditable) {
      ;(this.target as HTMLTextAreaElement).setSelectionRange(position, position)
      return
    }

    const range = this.createRange(this.target, { count: position })
    if (range === null) {
      return
    }

    range.collapse(false)
    const selection = window.getSelection()
    selection?.removeAllRanges()
    selection?.addRange(range)
  }

  private createRange(node: Node, chars: { count: number }, range?: Range): Range | null {
    if (range === undefined) {
      range = document.createRange()
      range.selectNode(node)
      range.setStart(node, 0)
    }

    if (chars.count === 0) {
      range.setEnd(node, chars.count)

      return range
    }

    if (node.nodeType === Node.TEXT_NODE) {
      const length = node.textContent?.length ?? 0
      if (length < chars.count) {
        chars.count -= length
      } else {
        range.setEnd(node, chars.count)
        chars.count = 0
      }

      return range
    }

    for (const child of Array.from(node.childNodes)) {
      range = this.createRange(child, chars, range) ?? range
      if (chars.count === 0) {
        break
      }
    }

    return range
  }
}

export default Caret
