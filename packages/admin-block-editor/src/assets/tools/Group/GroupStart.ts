import { API, BlockAPI, BlockTool, BlockToolConstructorOptions } from '@editorjs/editorjs'
import { IconBrackets } from '@codexteam/icons'
import './Group.css'
import make from '../utils/make'
import { GroupRegistry } from './GroupRegistry'

export interface GroupStartData {
  anchor?: string
  class?: string
}

/** Strip what would escape an HTML attribute value. */
const stripAttributeBreakers = (value: string): string => value.replace(/["<>]/g, '')

/**
 * Opening marker of a group: a `<div>` wrapper with optional anchor and
 * class, closed by the GroupEnd marker inserted with it. The blocks in
 * between stay ordinary top-level blocks, so the markdown round-trip stays
 * flat — the pair exports to bare `<div …>` / `</div>` lines that CommonMark
 * passes through while rendering the markdown between them.
 */
export default class GroupStart implements BlockTool {
  private api: API
  private block: BlockAPI
  private data: Required<GroupStartData>
  private readOnly: boolean

  /**
   * Empty data = the block comes from the toolbox: every other path (markdown
   * import, JSON load, undo re-render) passes both keys. A fresh group gets
   * its end marker and an empty paragraph to type into.
   */
  private isFresh: boolean

  static get toolbox() {
    return { icon: IconBrackets, title: 'Group' }
  }

  static get isReadOnlySupported(): boolean {
    return true
  }

  constructor({ data, api, block, readOnly }: BlockToolConstructorOptions<GroupStartData>) {
    this.api = api
    this.block = block
    this.readOnly = readOnly
    this.isFresh = Object.keys(data ?? {}).length === 0
    this.data = { anchor: data?.anchor ?? '', class: data?.class ?? '' }
  }

  render(): HTMLElement {
    const wrapper = make.element('div', 'pw-group-start')

    const label = make.element('span', 'pw-group-label', {}, IconBrackets)
    label.appendChild(document.createTextNode(this.api.i18n.t('Group')))
    wrapper.appendChild(label)

    wrapper.appendChild(
      this.input('pw-group-anchor', '#' + this.api.i18n.t('Anchor'), this.data.anchor, (value) => {
        this.data.anchor = value.replace(/[^a-z0-9_-]/gi, '')
      }),
    )
    wrapper.appendChild(
      this.input('pw-group-class', this.api.i18n.t('Class'), this.data.class, (value) => {
        this.data.class = stripAttributeBreakers(value)
      }),
    )

    return wrapper
  }

  private input(
    className: string,
    placeholder: string,
    value: string,
    apply: (value: string) => void,
  ): HTMLInputElement {
    const input = make.element('input', ['pw-group-input', className], {
      placeholder,
      value,
    }) as HTMLInputElement
    if (this.readOnly) {
      input.disabled = true
    }
    input.addEventListener('input', () => {
      apply(input.value)
      this.block.dispatchChange()
    })
    return input
  }

  save(): GroupStartData {
    // Both keys always present, so a re-render is never mistaken for a toolbox insertion.
    return { anchor: this.data.anchor, class: this.data.class }
  }

  rendered(): void {
    if (this.isFresh) {
      this.isFresh = false
      this.closeFreshGroup()
    }
    GroupRegistry.schedule(this.api)
  }

  moved(): void {
    GroupRegistry.schedule(this.api)
  }

  removed(): void {
    GroupRegistry.removePartnerOf(this.api, this.block.id)
    GroupRegistry.schedule(this.api)
  }

  /** A toolbox-inserted group opens ready to type into: end marker + empty paragraph. */
  private closeFreshGroup(): void {
    setTimeout(() => {
      const index = this.api.blocks.getBlockIndex(this.block.id)
      if (index < 0) return
      this.api.blocks.insert(GroupRegistry.END, {}, undefined, index + 1, false)
      this.api.blocks.insert('paragraph', {}, undefined, index + 1, true)
    })
  }

  static exportToMarkdown(data: GroupStartData): string {
    let attributes = ''
    if (data.anchor) {
      attributes += ` id="${stripAttributeBreakers(data.anchor)}"`
    }
    if (data.class) {
      attributes += ` class="${stripAttributeBreakers(data.class)}"`
    }
    return `<div${attributes}>`
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const trimmed = markdown.trim()
    const anchor = /\sid="([^"]*)"/.exec(trimmed)?.[1] ?? ''
    const className = /\sclass="([^"]*)"/.exec(trimmed)?.[1] ?? ''
    editor.blocks.insert(GroupRegistry.START, { anchor, class: className })
  }

  /** A lone `<div>` line whose only attributes are id/class; anything richer stays Raw. */
  static isItMarkdownExported(markdown: string): boolean {
    return /^<div(?:\s+(?:id|class)="[^"]*")*\s*>$/.test(markdown.trim())
  }
}
