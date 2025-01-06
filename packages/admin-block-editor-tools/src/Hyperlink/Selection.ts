/**
 * @typedef {SelectionUtils} SelectionUtils
 */
export default class SelectionUtils {
  public instance: Selection = null

  public selection: Selection = null

  /**
   * This property can store SelectionUtils's range for restoring later
   * @type {Range|null}
   */
  public savedSelectionRange: Range = null

  /**
   * Fake background is active
   *
   * @return {boolean}
   */
  public isFakeBackgroundEnabled = false

  /**
   * Native Document's commands for fake background
   */
  private readonly commandBackground: string = 'backColor'

  private readonly commandRemoveFormat: string = 'removeFormat'

  /**
   * Return first range
   * @return {Range|null}
   */
  static get range(): Range {
    const selection = window.getSelection()

    return selection && selection.rangeCount ? selection.getRangeAt(0) : null
  }

  /**
   * Returns selected text as String
   * @returns {string}
   */
  static get text(): string {
    return window.getSelection ? window.getSelection().toString() : ''
  }

  /**
   * Returns window SelectionUtils
   * {@link https://developer.mozilla.org/ru/docs/Web/API/Window/getSelection}
   * @return {Selection}
   */
  public static get(): Selection {
    return window.getSelection()
  }

  /**
   * Removes fake background
   */
  public removeFakeBackground() {
    if (!this.isFakeBackgroundEnabled) {
      return
    }

    this.isFakeBackgroundEnabled = false
    document.execCommand(this.commandRemoveFormat)
  }

  /**
   * Sets fake background
   */
  public setFakeBackground() {
    document.execCommand(this.commandBackground, false, '#a8d6ff')
    this.isFakeBackgroundEnabled = true
  }

  /**
   * Save SelectionUtils's range
   */
  public save(): void {
    this.savedSelectionRange = SelectionUtils.range
  }

  /**
   * Restore saved SelectionUtils's range
   */
  public restore(): void {
    if (!this.savedSelectionRange) {
      return
    }

    const sel = window.getSelection()

    sel.removeAllRanges()
    sel.addRange(this.savedSelectionRange)
  }

  /**
   * Clears saved selection
   */
  public clearSaved(): void {
    this.savedSelectionRange = null
  }

  /**
   * Collapse current selection
   */
  public collapseToEnd(): void {
    const sel = window.getSelection()
    const range = document.createRange()

    range.selectNodeContents(sel.focusNode)
    range.collapse(false)
    sel.removeAllRanges()
    sel.addRange(range)
  }

  /**
   * Looks ahead to find passed tag from current selection
   *
   * @param  {string} tagName       - tag to found
   * @param  {string} [className]   - tag's class name
   * @param  {number} [searchDepth] - count of tags that can be included. For better performance.
   * @returns {HTMLElement|null}
   */
  public findParentTag(tagName: string, className?: string, searchDepth = 10): HTMLElement | null {
    const selection = window.getSelection();
    let parentTag = null;

    /**
     * If selection is missing or no anchorNode or focusNode were found then return null
     */
    if (!selection || !selection.anchorNode || !selection.focusNode) {
      return null;
    }

    /**
     * Define Nodes for start and end of selection
     */
    const boundNodes = [
      /** the Node in which the selection begins */
      selection.anchorNode as HTMLElement,
      /** the Node in which the selection ends */
      selection.focusNode as HTMLElement,
    ];

    /**
     * For each selection parent Nodes we try to find target tag [with target class name]
     * It would be saved in parentTag variable
     */
    boundNodes.forEach((parent) => {
      /** Reset tags limit */
      let searchDepthIterable = searchDepth;

      while (searchDepthIterable > 0 && parent.parentNode) {
        /**
         * Check tag's name
         */
        if (parent.tagName === tagName) {
          /**
           * Save the result
           */
          parentTag = parent;

          /**
           * Optional additional check for class-name mismatching
           */
          if (className && parent.classList && !parent.classList.contains(className)) {
            parentTag = null;
          }

          /**
           * If we have found required tag with class then go out from the cycle
           */
          if (parentTag) {
            break;
          }
        }

        /**
         * Target tag was not found. Go up to the parent and check it
         */
        parent = parent.parentNode as HTMLElement;
        searchDepthIterable--;
      }
    });

    /**
     * Return found tag or null
     */
    return parentTag;
  }
}
