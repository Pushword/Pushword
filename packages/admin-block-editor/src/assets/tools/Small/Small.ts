import type {
  API,
  InlineTool,
  SanitizerConfig,
  InlineToolConstructorOptions,
} from '@editorjs/editorjs'
import { unwrapTag, wrapInTag } from '../utils/inlineTag'

export default class Small implements InlineTool {
  private button: HTMLButtonElement | undefined
  private tag: string = 'SMALL'
  private api: API

  public static isInline = true

  public constructor(options: InlineToolConstructorOptions) {
    this.api = options.api
  }

  public render(): HTMLElement {
    this.button = document.createElement('button')
    this.button.type = 'button'
    this.button.classList.add(this.api.styles.inlineToolButton)
    this.button.innerHTML = 'Aa'

    return this.button
  }

  public surround(range: Range): void {
    if (!range) return

    const termWrapper = this.api.selection.findParentTag(this.tag)

    if (termWrapper) {
      unwrapTag(this.api, termWrapper)
    } else {
      wrapInTag(this.api, range, this.tag)
    }
  }

  /**
   * Check and change Term's state for current selection
   */
  public checkState(): boolean {
    const termTag = this.api.selection.findParentTag(this.tag)

    this.button?.classList.toggle(this.api.styles.inlineToolButtonActive, !!termTag)

    return !!termTag
  }

  public static get sanitize(): SanitizerConfig {
    return {
      u: {},
    }
  }
}
