// @deprecated use this.editor.selection or this.api.selection

/**
 * @typedef {SelectionUtils} SelectionUtils
 */
export default class SelectionUtils {
  /** store SelectionUtils's range for restoring later */
  public savedSelectionRange: Range | null = null

  static get range(): Range | null {
    const selection = window.getSelection()

    return selection && selection.rangeCount ? selection.getRangeAt(0) : null
  }

  public save(): void {
    this.savedSelectionRange = SelectionUtils.range?.cloneRange() ?? null
  }

  public restore(): void {
    if (!this.savedSelectionRange) return

    const sel = window.getSelection()
    if (!sel) return

    sel.removeAllRanges()
    sel.addRange(this.savedSelectionRange)
  }

  public collapseToEnd(): void {
    const sel = window.getSelection()
    if (sel?.rangeCount) sel.collapseToEnd()
  }
}
