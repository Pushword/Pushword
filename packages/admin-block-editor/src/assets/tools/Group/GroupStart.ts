import { API, BlockAPI, BlockTool, BlockToolConstructorOptions } from '@editorjs/editorjs'
import { IconBrackets } from '@codexteam/icons'
import './Group.css'
import make from '../utils/make'
import { GroupRegistry } from './GroupRegistry'
import {
  buildStartCall,
  GroupKind,
  kindOf,
  startCallArguments,
  startSyntax,
} from './GroupSyntax'

export interface GroupStartData {
  anchor?: string
  class?: string
  /** Wrap the blocks in the collapsible show-more block rather than a plain div. */
  collapsible?: boolean
  /** Read from the legacy `<!--start-show-more-->` pair, and written back as one. */
  legacy?: boolean
}

/** Strip what would escape an HTML attribute value or a Twig string literal. */
const stripAttributeBreakers = (value: string): string => value.replace(/["'<>]/g, '')

/**
 * Opening marker of a group, closed by the GroupEnd inserted with it. The blocks
 * in between stay ordinary top-level blocks, so the markdown round-trip stays
 * flat — the pair exports to two lines the renderer knows how to wrap with.
 *
 * Unchecked, that is a bare `<div …>` / `</div>` CommonMark passes through.
 * Checked, it is `{{ startShowMore(…) }}` / `{{ endShowMore() }}`, the collapsible
 * block: the anchor becomes its id and the class lands on its wrapper — which
 * holds the toggle and the button, so layout classes belong on a plain group
 * nested inside, not here.
 */
export default class GroupStart implements BlockTool {
  private api: API
  private block: BlockAPI
  private data: Required<GroupStartData>
  private readOnly: boolean

  /**
   * Empty data = the block comes from the toolbox: every other path (markdown
   * import, JSON load, undo re-render) passes every key. A fresh group gets
   * its end marker and an empty paragraph to type into.
   */
  private isFresh: boolean

  private classInput?: HTMLInputElement

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
    this.data = {
      anchor: data?.anchor ?? '',
      class: data?.class ?? '',
      collapsible: data?.collapsible ?? false,
      legacy: data?.legacy ?? false,
    }
  }

  render(): HTMLElement {
    const wrapper = make.element('div', 'pw-group-start')
    wrapper.dataset.pwGroupKind = this.kind()

    const label = make.element('span', 'pw-group-label', {}, IconBrackets)
    label.appendChild(document.createTextNode(this.api.i18n.t('Group')))
    wrapper.appendChild(label)

    wrapper.appendChild(
      this.input('pw-group-anchor', '#' + this.api.i18n.t('Anchor'), this.data.anchor, (value) => {
        this.data.anchor = value.replace(/[^a-z0-9_-]/gi, '')
      }),
    )
    this.classInput = this.input('pw-group-class', '', this.data.class, (value) => {
      this.data.class = stripAttributeBreakers(value)
    })
    wrapper.appendChild(this.classInput)
    this.refreshClassPlaceholder()

    wrapper.appendChild(this.collapsibleToggle(wrapper))

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

  /**
   * The class means two different things, so say which: on a plain group it
   * dresses the `<div>` wrapping the blocks, on a collapsible one it dresses the
   * show-more wrapper, whose children are the toggle, the content and the button.
   */
  private refreshClassPlaceholder(): void {
    if (this.classInput === undefined) return

    this.classInput.placeholder = this.data.collapsible
      ? this.api.i18n.t('Class of the collapsible')
      : this.api.i18n.t('Class')
  }

  private collapsibleToggle(wrapper: HTMLElement): HTMLElement {
    const label = make.element('label', 'pw-group-toggle')
    const input = make.element('input', 'pw-group-checkbox', {
      type: 'checkbox',
    }) as HTMLInputElement
    input.checked = this.data.collapsible
    input.disabled = this.readOnly
    input.addEventListener('change', () => {
      this.data.collapsible = input.checked
      wrapper.dataset.pwGroupKind = this.kind()
      this.refreshClassPlaceholder()
      // The closing marker has to be spelled like its opening one, and it is a
      // block of its own — nothing else would tell it the pair just changed.
      GroupRegistry.updatePartnerOf(this.api, this.block.id, {
        collapsible: this.data.collapsible,
        legacy: this.data.legacy,
        args: '',
      })
      this.block.dispatchChange()
      GroupRegistry.schedule(this.api)
    })

    label.appendChild(input)
    label.appendChild(document.createTextNode(this.api.i18n.t('Collapsible')))

    return label
  }

  private kind(): GroupKind {
    return this.data.collapsible ? 'showMore' : 'div'
  }

  save(): GroupStartData {
    // Every key always present, so a re-render is never mistaken for a toolbox insertion.
    return {
      anchor: this.data.anchor,
      class: this.data.class,
      collapsible: this.data.collapsible,
      legacy: this.data.legacy,
    }
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
      this.api.blocks.insert(
        GroupRegistry.END,
        { collapsible: false, legacy: false, args: '' },
        undefined,
        index + 1,
        false,
      )
      this.api.blocks.insert('paragraph', {}, undefined, index + 1, true)
    })
  }

  static exportToMarkdown(data: GroupStartData): string {
    const anchor = stripAttributeBreakers(data.anchor ?? '')
    const className = stripAttributeBreakers(data.class ?? '')

    if (true !== data.collapsible) {
      let attributes = ''
      if ('' !== anchor) {
        attributes += ` id="${anchor}"`
      }
      if ('' !== className) {
        attributes += ` class="${className}"`
      }

      return `<div${attributes}>`
    }

    // A legacy pair carries nothing the comment could hold, so it goes back as
    // the comment it came from: opening a page must not rewrite it.
    if (true === data.legacy && '' === anchor && '' === className) {
      return '<!--start-show-more-->'
    }

    return buildStartCall(anchor, className)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const trimmed = markdown.trim()
    const syntax = startSyntax(trimmed)

    if ('div' === syntax) {
      editor.blocks.insert(GroupRegistry.START, {
        anchor: /\sid="([^"]*)"/.exec(trimmed)?.[1] ?? '',
        class: /\sclass="([^"]*)"/.exec(trimmed)?.[1] ?? '',
        collapsible: false,
        legacy: false,
      })

      return
    }

    const [id, className] = 'twig' === syntax ? startCallArguments(trimmed) : []
    editor.blocks.insert(GroupRegistry.START, {
      anchor: id ?? '',
      class: className ?? '',
      collapsible: true,
      legacy: 'comment' === syntax,
    })
  }

  static isItMarkdownExported(markdown: string): boolean {
    return null !== startSyntax(markdown)
  }

  /** What the line opens, so a closer only ever closes its own kind. */
  static kindOf(markdown: string): GroupKind | null {
    const syntax = startSyntax(markdown)

    return null === syntax ? null : kindOf(syntax)
  }
}
