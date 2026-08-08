import { API, BlockAPI, BlockTool, BlockToolConstructorOptions } from '@editorjs/editorjs'
import { IconBrackets } from '@codexteam/icons'
import './Group.css'
import make from '../utils/make'
import { GroupRegistry } from './GroupRegistry'
import { buildEndCall, endCallArguments, endSyntax, GroupKind, kindOf } from './GroupSyntax'

export interface GroupEndData {
  /** Mirrors its GroupStart: the pair is spelled the same on both sides. */
  collapsible?: boolean
  legacy?: boolean
  /** Arguments of `{{ endShowMore(…) }}`, verbatim — a hand-set background lives there. */
  args?: string
}

/**
 * Closing marker of a group. Never offered in the toolbox: it is inserted
 * with its GroupStart and follows its fate — deleting either marker removes
 * the other, the wrapped blocks stay.
 */
export default class GroupEnd implements BlockTool {
  private api: API
  private block: BlockAPI
  private data: Required<GroupEndData>

  static get isReadOnlySupported(): boolean {
    return true
  }

  constructor({ data, api, block }: BlockToolConstructorOptions<GroupEndData>) {
    this.api = api
    this.block = block
    this.data = {
      collapsible: data?.collapsible ?? false,
      legacy: data?.legacy ?? false,
      args: data?.args ?? '',
    }
  }

  render(): HTMLElement {
    const wrapper = make.element('div', 'pw-group-end')
    wrapper.dataset.pwGroupKind = this.data.collapsible ? 'showMore' : 'div'
    const label = make.element('span', 'pw-group-label', {}, IconBrackets)
    label.appendChild(
      document.createTextNode(
        this.api.i18n.t(this.data.collapsible ? 'End of collapsible' : 'End of group'),
      ),
    )
    wrapper.appendChild(label)
    return wrapper
  }

  save(): GroupEndData {
    return { collapsible: this.data.collapsible, legacy: this.data.legacy, args: this.data.args }
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

  static exportToMarkdown(data?: GroupEndData): string {
    if (true !== data?.collapsible) {
      return '</div>'
    }

    return true === data.legacy && '' === (data.args ?? '')
      ? '<!--end-show-more-->'
      : buildEndCall(data.args ?? '')
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const syntax = endSyntax(markdown)

    editor.blocks.insert(GroupRegistry.END, {
      collapsible: 'div' !== syntax,
      legacy: 'comment' === syntax,
      args: 'twig' === syntax ? endCallArguments(markdown) : '',
    })
  }

  /**
   * A candidate closer. Whether it really is a marker is settled by
   * GroupNesting, which only lets it close a group of its own kind that is
   * actually open — a `</div>` ending hand-written HTML stays Raw, like its
   * opener, and so does a stray `<!--end-show-more-->`.
   */
  static isItMarkdownExported(markdown: string): boolean {
    return null !== endSyntax(markdown)
  }

  static kindOf(markdown: string): GroupKind | null {
    const syntax = endSyntax(markdown)

    return null === syntax ? null : kindOf(syntax)
  }
}
