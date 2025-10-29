// @deprecated use this.editor.selection or this.api.selection

/**
 * @typedef {SelectionUtils} SelectionUtils
 */
export default class SelectionUtils {
  public instance: Selection | null = null

  public selection: Selection | null = null

  /** store SelectionUtils's range for restoring later */
  public savedSelectionRange: Range | null = null

  /** Fake background is active */
  public isFakeBackgroundEnabled: boolean = false

  static get range(): Range | null {
    const selection = window.getSelection()

    return selection && selection.rangeCount ? selection.getRangeAt(0) : null
  }

  static get text(): string {
    return window.getSelection()?.toString() || ''
  }

  // public static get(): Selection | null {
  //   return window.getSelection()
  // }

  public removeFakeBackground() {
    if (!this.isFakeBackgroundEnabled) {
      return
    }

    this.isFakeBackgroundEnabled = false
    document.execCommand('removeFormat')
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

  public clearSaved(): void {
    this.savedSelectionRange = null
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

  public findParentTag(
    tagName: string,
    className?: string,
    searchDepth = 10,
  ): HTMLElement | null {
    const selection = window.getSelection()
    let parentTag = null

    if (!selection || !selection.anchorNode || !selection.focusNode) {
      return null
    }

    // Define Nodes for start and end of selection
    const boundNodes = [
      selection.anchorNode as HTMLElement,
      selection.focusNode as HTMLElement,
    ]

    // for each selection parent Nodes
    // try to find target tag [with target class name]
    boundNodes.forEach((parent) => {
      // Reset tags limit
      let searchDepthIterable = searchDepth

      while (searchDepthIterable > 0 && parent.parentNode) {
        if (parent.tagName === tagName) {
          if (className && parent.classList && !parent.classList.contains(className)) {
            continue
          }
          parentTag = parent
          break
        }

        // Target tag was not found. Go up to the parent and check it
        parent = parent.parentNode as HTMLElement
        searchDepthIterable--
      }
    })

    return parentTag
  }
}
