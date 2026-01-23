import { m as make } from "./make-D456KYKV.mjs";
import { IconLink, IconUnlink } from "@codexteam/icons";
import { l as logger } from "./logger-5AkeQ-mP.mjs";
class SelectionUtils {
  constructor() {
    this.instance = null;
    this.selection = null;
    this.savedSelectionRange = null;
    this.isFakeBackgroundEnabled = false;
    this.commandBackground = "backColor";
    this.commandRemoveFormat = "removeFormat";
  }
  /**
   * Return first range
   * @return {Range|null}
   */
  static get range() {
    const selection = window.getSelection();
    return selection && selection.rangeCount ? selection.getRangeAt(0) : null;
  }
  /**
   * Returns selected text as String
   * @returns {string}
   */
  static get text() {
    return window.getSelection ? window.getSelection().toString() : "";
  }
  /**
   * Returns window SelectionUtils
   * {@link https://developer.mozilla.org/ru/docs/Web/API/Window/getSelection}
   * @return {Selection}
   */
  static get() {
    return window.getSelection();
  }
  /**
   * Removes fake background
   */
  removeFakeBackground() {
    if (!this.isFakeBackgroundEnabled) {
      return;
    }
    this.isFakeBackgroundEnabled = false;
    document.execCommand(this.commandRemoveFormat);
  }
  /**
   * Sets fake background
   */
  setFakeBackground() {
    document.execCommand(this.commandBackground, false, "#a8d6ff");
    this.isFakeBackgroundEnabled = true;
  }
  /**
   * Save SelectionUtils's range
   */
  save() {
    this.savedSelectionRange = SelectionUtils.range;
  }
  /**
   * Restore saved SelectionUtils's range
   */
  restore() {
    if (!this.savedSelectionRange) {
      return;
    }
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(this.savedSelectionRange);
  }
  /**
   * Clears saved selection
   */
  clearSaved() {
    this.savedSelectionRange = null;
  }
  /**
   * Collapse current selection
   */
  collapseToEnd() {
    const sel = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(sel.focusNode);
    range.collapse(false);
    sel.removeAllRanges();
    sel.addRange(range);
  }
  /**
   * Looks ahead to find passed tag from current selection
   *
   * @param  {string} tagName       - tag to found
   * @param  {string} [className]   - tag's class name
   * @param  {number} [searchDepth] - count of tags that can be included. For better performance.
   * @returns {HTMLElement|null}
   */
  findParentTag(tagName, className, searchDepth = 10) {
    const selection = window.getSelection();
    let parentTag = null;
    if (!selection || !selection.anchorNode || !selection.focusNode) {
      return null;
    }
    const boundNodes = [
      /** the Node in which the selection begins */
      selection.anchorNode,
      /** the Node in which the selection ends */
      selection.focusNode
    ];
    boundNodes.forEach((parent) => {
      let searchDepthIterable = searchDepth;
      while (searchDepthIterable > 0 && parent.parentNode) {
        if (parent.tagName === tagName) {
          parentTag = parent;
          if (className && parent.classList && !parent.classList.contains(className)) {
            parentTag = null;
          }
          if (parentTag) {
            break;
          }
        }
        parent = parent.parentNode;
        searchDepthIterable--;
      }
    });
    return parentTag;
  }
}
const _Hyperlink = class _Hyperlink {
  constructor({ api }) {
    this.availableDesign = {
      bouton: "link-btn",
      discret: "ninja"
      //text-current no-underline border-0 font-normal
    };
    this.nodes = {
      wrapper: null,
      input: null,
      selectDesign: null,
      hideForBot: null,
      targetBlank: null,
      button: null,
      linkButton: null,
      unlinkButton: null
    };
    this.inputOpened = false;
    this.anchorTag = null;
    this.api = api;
    this.selection = new SelectionUtils();
  }
  render() {
    logger.debug("Hyperlink render");
    this.nodes.button = document.createElement("button");
    this.nodes.button.type = "button";
    this.nodes.button.classList.add(this.api.styles.inlineToolButton);
    this.nodes.button.innerHTML = IconLink;
    return this.nodes.button;
  }
  renderActions() {
    logger.debug("Hyperlink renderActions", { hasInput: !!this.nodes.input });
    this.nodes.input = make.element("input", this.api.styles.input, {
      placeholder: "https://..."
    });
    this.nodes.suggester = make.element("div", "textSuggester", { style: "display:none" });
    const options = { highlight: true, dispMax: 20, dispAllKey: true };
    new Suggest.Local(
      this.nodes.input,
      this.nodes.suggester,
      window.pagesUriList ?? [],
      options
    );
    this.nodes.hideForBot = make.switchInput("hideForBot", this.api.i18n.t("Obfusquer"));
    this.nodes.targetBlank = make.switchInput(
      "targetBlank",
      this.api.i18n.t("Nouvel onglet")
    );
    this.nodes.selectDesign = make.element(
      "select",
      this.api.styles.input
    );
    make.option(this.nodes.selectDesign, "", this.api.i18n.t("Style"), {
      style: "opacity: 0.5"
    });
    for (const [key, value] of Object.entries(this.availableDesign)) {
      make.option(this.nodes.selectDesign, value, key);
    }
    this.nodes.wrapper = document.createElement("div");
    this.nodes.wrapper.classList.add("link-options-wrapper");
    this.nodes.wrapper.append(
      this.nodes.input,
      this.nodes.suggester,
      this.nodes.hideForBot,
      this.nodes.targetBlank,
      this.nodes.selectDesign
    );
    this.nodes.wrapper.addEventListener("change", () => {
      this.updateLink();
    });
    this.nodes.wrapper.addEventListener("copy", async (e) => {
      var _a;
      logger.debug("Hyperlink copy", { hasAnchorTag: !!this.anchorTag });
      await navigator.clipboard.write([
        new ClipboardItem({
          "text/html": new Blob([((_a = this.anchorTag) == null ? void 0 : _a.outerHTML) || ""], { type: "text/html" }),
          "text/plain": new Blob([this.nodes.input.value], { type: "text/plain" })
        })
      ]);
      e.preventDefault();
    });
    this.nodes.input.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        logger.debug("Hyperlink Enter pressed");
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        this.updateLink();
        this.closeActions();
      }
    });
    logger.debug("Hyperlink renderActions completed");
    return this.nodes.wrapper;
  }
  checkState() {
    var _a;
    logger.debug("Hyperlink checkState");
    const anchorTag = this.anchorTag || this.api.selection.findParentTag("A");
    if (!anchorTag) {
      this.showUnlink(false);
      return false;
    }
    if (!anchorTag.innerText.includes(((_a = window.getSelection()) == null ? void 0 : _a.toString()) || "")) {
      this.showUnlink(true);
      return false;
    }
    this.showUnlink();
    this.anchorTag = anchorTag;
    this.openActions();
    this.updateActionValues(anchorTag);
    setTimeout(() => this.nodes.input.focus(), 0);
    logger.debug("Hyperlink checkState completed");
    return true;
  }
  surround(range) {
    logger.debug("Hyperlink surround", {
      hasRange: !!range,
      hasAnchorTag: !!this.anchorTag
    });
    if (!range) {
      this.toggleActions();
      return;
    }
    if (this.inputOpened) {
      this.selection.restore();
      this.selection.removeFakeBackground();
    }
    const termWrapper = this.api.selection.findParentTag("A") || this.anchorTag;
    logger.debug("Hyperlink termWrapper check", {
      hasTermWrapper: !!termWrapper,
      inputOpened: this.inputOpened
    });
    if (termWrapper) {
      this.unlink(termWrapper);
      this.closeActions();
      return;
    }
    logger.debug("Hyperlink creating new anchor tag");
    this.anchorTag = document.createElement("A");
    this.anchorTag.appendChild(range.extractContents());
    range.insertNode(this.anchorTag);
    this.api.selection.expandToTag(this.anchorTag);
    this.selection.setFakeBackground();
    this.selection.save();
    this.openActions(true);
  }
  showUnlink(showUnlink = true) {
    var _a, _b;
    if (showUnlink) {
      (_a = this.nodes.button) == null ? void 0 : _a.classList.add(this.api.styles.inlineToolButtonActive);
      this.nodes.button.innerHTML = IconUnlink;
      return;
    }
    this.nodes.button.innerHTML = IconLink;
    (_b = this.nodes.button) == null ? void 0 : _b.classList.remove(this.api.styles.inlineToolButtonActive);
  }
  updateActionValues(anchorTag) {
    logger.debug("Hyperlink updateActionValues");
    if (!this.nodes.input) return;
    const hrefAttr = anchorTag.getAttribute("href");
    this.nodes.input.value = hrefAttr ? hrefAttr : "";
    const relAttr = anchorTag.getAttribute("rel");
    this.nodes.hideForBot.querySelector("input").checked = !!relAttr;
    const targetAttr = anchorTag.getAttribute("target");
    this.nodes.targetBlank.querySelector("input").checked = !!targetAttr;
    const designAttr = anchorTag.getAttribute("class");
    this.nodes.selectDesign.value = designAttr ? designAttr : "";
    logger.debug("Hyperlink updateActionValues completed");
  }
  get shortcut() {
    return "CMD+K";
  }
  static get isInline() {
    return true;
  }
  static get sanitize() {
    return {
      a: {
        href: true,
        target: true,
        rel: true,
        class: true
      }
    };
  }
  clear() {
    logger.debug("Hyperlink clear");
    if (this.anchorTag) this.anchorTag.style = "";
    this.selection.removeFakeBackground();
  }
  toggleActions() {
    logger.debug("Hyperlink toggleActions", { inputOpened: this.inputOpened });
    if (!this.inputOpened) {
      this.openActions(true);
    } else {
      this.closeActions();
    }
  }
  openActions(needFocus = false) {
    logger.debug("Hyperlink openActions", { needFocus });
    this.nodes.wrapper.style.display = "block";
    if (this.anchorTag) {
      this.api.selection.expandToTag(this.anchorTag);
      this.api.selection.setFakeBackground();
      this.api.selection.save();
    }
    if (needFocus) {
      this.nodes.input.focus();
    }
    this.inputOpened = true;
  }
  closeActions() {
    logger.debug("Hyperlink closeActions", {
      isFakeBackgroundEnabled: this.selection.isFakeBackgroundEnabled
    });
    if (this.selection.isFakeBackgroundEnabled) {
      const currentSelection = new SelectionUtils();
      currentSelection.save();
      this.selection.restore();
      this.selection.removeFakeBackground();
      this.selection.collapseToEnd();
    }
    const value = this.nodes.input.value || "";
    if (!value.trim()) this.unlink(this.anchorTag);
    this.inputOpened = false;
    this.api.inlineToolbar.close();
  }
  updateLink() {
    if (!this.anchorTag) return null;
    const href = this.nodes.input.value.trim() || "";
    this.anchorTag.setAttribute("href", href);
    const target = this.nodes.targetBlank.querySelector("input").checked ? "_blank" : "";
    if (target) {
      this.anchorTag.setAttribute("target", target);
    } else {
      this.anchorTag.removeAttribute("target");
    }
    const rel = this.nodes.hideForBot.querySelector("input").checked ? "obfuscate" : "";
    if (rel) {
      this.anchorTag.setAttribute("rel", rel);
    } else {
      this.anchorTag.removeAttribute("rel");
    }
    const design = this.nodes.selectDesign.value || "";
    if (design) {
      this.anchorTag.className = design;
    } else {
      this.anchorTag.removeAttribute("class");
    }
    return this.anchorTag;
  }
  unlink(termWrapper) {
    var _a;
    logger.debug("Hyperlink unlink", {
      hasTermWrapper: !!termWrapper,
      hasAnchorTag: !!this.anchorTag
    });
    if (!termWrapper) return;
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
    range.collapse();
    sel.addRange(range);
    logger.debug("Hyperlink unlink completed");
  }
};
_Hyperlink.title = "Link";
let Hyperlink = _Hyperlink;
export {
  Hyperlink as default
};
