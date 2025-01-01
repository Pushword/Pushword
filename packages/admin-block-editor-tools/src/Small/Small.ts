
import {type API, type InlineTool, type SanitizerConfig} from "@editorjs/editorjs";
import {type InlineToolConstructorOptions} from "@editorjs/editorjs/types/tools/inline-tool";

export default class Small implements InlineTool {

  private button: HTMLButtonElement | undefined
  private tag: string = 'SMALL';
  private api: API
  private iconClasses: {base: string, active: string}
  public constructor(options: InlineToolConstructorOptions) {
    this.api = options.api;
    this.iconClasses = {
      base: this.api.styles.inlineToolButton,
      active: this.api.styles.inlineToolButtonActive,
    };
  }
  public static isInline = true;

  public render(): HTMLElement {
    this.button = document.createElement('button');
    this.button.type = 'button';
    this.button.classList.add(this.iconClasses.base);
    this.button.innerHTML = 'Aa';

    return this.button;
  }

  /**
   * Wrap/Unwrap selected fragment
   *
   * @param {Range} range - selected fragment
   */
  public surround(range: Range): void {
    if (!range) {
      return;
    }

    const termWrapper = this.api.selection.findParentTag(this.tag);

    /**
     * If start or end of selection is in the highlighted block
     */
    if (termWrapper) {
      this.unwrap(termWrapper);
    } else {
      this.wrap(range);
    }
  }

  public wrap(range: Range) {
    const u = document.createElement(this.tag);
    u.appendChild(range.extractContents());
    range.insertNode(u);
    this.api.selection.expandToTag(u);
  }

  public unwrap(termWrapper: HTMLElement): void {

    this.api.selection.expandToTag(termWrapper);

    const sel = window.getSelection();
    if (!sel) {
      return;
    }
    const range = sel.getRangeAt(0);
    if (!range) {
      return
    }

    const unwrappedContent = range.extractContents();
    if (!unwrappedContent) {
      return
    }

    // Remove empty term-tag
    termWrapper.parentNode?.removeChild(termWrapper);

    range.insertNode(unwrappedContent);

    sel.removeAllRanges();
    sel.addRange(range);
  }

  /**
   * Check and change Term's state for current selection
   */
  public checkState(): boolean {
    const termTag = this.api.selection.findParentTag(this.tag);

    this.button?.classList.toggle(this.iconClasses.active, !!termTag);

    return !!termTag
  }

  public static get sanitize(): SanitizerConfig {
    return {
      u: { },
    };
  }
}