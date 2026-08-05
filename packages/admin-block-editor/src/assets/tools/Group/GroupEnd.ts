import { API, BlockAPI, BlockTool, BlockToolConstructorOptions } from '@editorjs/editorjs'
import { IconBrackets } from '@codexteam/icons'
import './Group.css'
import make from '../utils/make'
import { GroupRegistry } from './GroupRegistry'

/**
 * Closing marker of a group. Never offered in the toolbox: it is inserted
 * with its GroupStart and follows its fate — deleting either marker removes
 * the other, the wrapped blocks stay.
 */
export default class GroupEnd implements BlockTool {
  private api: API
  private block: BlockAPI

  static get isReadOnlySupported(): boolean {
    return true
  }

  constructor({ api, block }: BlockToolConstructorOptions) {
    this.api = api
    this.block = block
  }

  render(): HTMLElement {
    const wrapper = make.element('div', 'pw-group-end')
    const label = make.element('span', 'pw-group-label', {}, IconBrackets)
    label.appendChild(document.createTextNode(this.api.i18n.t('End of group')))
    wrapper.appendChild(label)
    return wrapper
  }

  save(): Record<string, never> {
    return {}
  }

  rendered(): void {
    GroupRegistry.schedule(this.api)
  }

  moved(): void {
    GroupRegistry.schedule(this.api)
  }

  removed(): void {
    GroupRegistry.removePartnerOf(this.api, this.block.id)
    GroupRegistry.schedule(this.api)
  }

  static exportToMarkdown(): string {
    return '</div>'
  }

  static importFromMarkdown(editor: API): void {
    editor.blocks.insert(GroupRegistry.END, {})
  }

  /**
   * A candidate closer. Whether it really is a marker is settled by
   * GroupNesting, which only lets it close a `<div>` GroupStart opened —
   * a `</div>` ending hand-written HTML stays Raw, like its opener.
   */
  static isItMarkdownExported(markdown: string): boolean {
    return markdown.trim() === '</div>'
  }
}
