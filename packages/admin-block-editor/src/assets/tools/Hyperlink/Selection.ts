// @deprecated use this.editor.selection or this.api.selection

/**
 * @typedef {SelectionUtils} SelectionUtils
 */
export default class SelectionUtils {
  /** store SelectionUtils's range for restoring later */
  public savedSelectionRange: Range | null = null

  /** Fake background is active */
  public isFakeBackgroundEnabled: boolean = false

  static get range(): Range | null {
    const selection = window.getSelection()

    return selection && selection.rangeCount ? selection.getRangeAt(0) : null
  }

  public removeFakeBackground() {
    if (!this.isFakeBackgroundEnabled) {
      return
    }

    // Undo the backColor set by setFakeBackground. `removeFormat` would work too,
    // but it also strips every inline tag of the selection (bold, marker, code…).
    document.execCommand('backColor', false, 'transparent')
    this.isFakeBackgroundEnabled = false
  }

  public setFakeBackground() {
    document.execCommand('backColor', false, '#a8d6ff')
    this.isFakeBackgroundEnabled = true
  }

  public save(): void {
    this.savedSelectionRange = SelectionUtils.range
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
    if (!sel || !sel.focusNode) return
    const range = document.createRange()

    range.selectNodeContents(sel.focusNode)
    range.collapse(false)
    sel.removeAllRanges()
    sel.addRange(range)
  }
}
