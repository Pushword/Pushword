const _Small = class _Small {
  constructor(options) {
    this.tag = "SMALL";
    this.api = options.api;
  }
  render() {
    this.button = document.createElement("button");
    this.button.type = "button";
    this.button.classList.add(this.api.styles.inlineToolButton);
    this.button.innerHTML = "Aa";
    return this.button;
  }
  /**
   * Wrap/Unwrap selected fragment
   *
   * @param {Range} range - selected fragment
   */
  surround(range) {
    if (!range) return;
    const termWrapper = this.api.selection.findParentTag(this.tag);
    if (termWrapper) {
      this.unwrap(termWrapper);
    } else {
      this.wrap(range);
    }
  }
  wrap(range) {
    const u = document.createElement(this.tag);
    u.appendChild(range.extractContents());
    range.insertNode(u);
    this.api.selection.expandToTag(u);
  }
  unwrap(termWrapper) {
    var _a;
    this.api.selection.expandToTag(termWrapper);
    const sel = window.getSelection();
    if (!sel) return;
    const range = sel.getRangeAt(0);
    if (!range) return;
    const unwrappedContent = range.extractContents();
    if (!unwrappedContent) return;
    (_a = termWrapper.parentNode) == null ? void 0 : _a.removeChild(termWrapper);
    range.insertNode(unwrappedContent);
    sel.removeAllRanges();
    sel.addRange(range);
  }
  /**
   * Check and change Term's state for current selection
   */
  checkState() {
    var _a;
    const termTag = this.api.selection.findParentTag(this.tag);
    (_a = this.button) == null ? void 0 : _a.classList.toggle(this.api.styles.inlineToolButtonActive, !!termTag);
    return !!termTag;
  }
  static get sanitize() {
    return {
      u: {}
    };
  }
};
_Small.isInline = true;
let Small = _Small;
export {
  Small as default
};
