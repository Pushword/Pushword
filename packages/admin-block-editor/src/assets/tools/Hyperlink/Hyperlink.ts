import SelectionUtils from './Selection'
import make from '../utils/make'
import { IconLink, IconUnlink } from '@codexteam/icons'
import { API } from '@editorjs/editorjs'
import './Hyperlink.css'

import { Suggest } from '../../../../../admin/src/Resources/assets/suggest.js'

interface HyperlinkNodes {
  wrapper: HTMLElement | null
  input: HTMLInputElement | null
  selectDesign: HTMLSelectElement | null
  selectRel: HTMLSelectElement | null
  targetBlank: HTMLElement | null
  button: HTMLButtonElement | null
  linkButton: HTMLButtonElement | null
  unlinkButton: HTMLButtonElement | null
  suggester?: HTMLElement
}

declare global {
  interface Window {
    pagesUriList?: string[]
  }
}

export default class Hyperlink {
  static title = 'Link'

  private static readonly defaultDesigns: Record<string, string> = {
    Button: 'link-btn',
    'Button outline': 'link-btn-outline',
    Discreet: 'ninja',
  }

  /**
   * Label => rel value. `obfuscate` is Pushword's own: HtmlObfuscateLink matches
   * `rel="obfuscate"` exactly, so it cannot be combined with a real rel — one
   * value per link, which is what a select gives.
   */
  private static readonly defaultRels: Record<string, string> = {
    Obfuscate: 'obfuscate',
    nofollow: 'nofollow',
    'nofollow sponsored': 'nofollow sponsored',
    'nofollow ugc': 'nofollow ugc',
  }

  private api: API
  private availableDesigns: Record<string, string>
  private availableRels: Record<string, string>

  private nodes: HyperlinkNodes = {
    wrapper: null,
    input: null,
    selectDesign: null,
    selectRel: null,
    targetBlank: null,
    button: null,
    linkButton: null,
    unlinkButton: null,
  }

  private inputOpened = false
  private anchorTag: HTMLElement | null = null
  private selection: SelectionUtils

  constructor({
    api,
    config,
  }: {
    api: API
    config?: {
      availableDesigns?: Record<string, string>
      availableRels?: Record<string, string>
    }
  }) {
    this.api = api
    this.availableDesigns = config?.availableDesigns ?? Hyperlink.defaultDesigns
    this.availableRels = config?.availableRels ?? Hyperlink.defaultRels
    this.selection = new SelectionUtils()
  }

  render(): HTMLElement {
    this.nodes.button = document.createElement('button') as HTMLButtonElement
    this.nodes.button.type = 'button'
    this.nodes.button.classList.add(this.api.styles.inlineToolButton)
    this.nodes.button.innerHTML = IconLink
    return this.nodes.button
  }

  renderActions(): HTMLElement {
    this.nodes.input = make.element('input', [this.api.styles.input, 'link-options__url'], {
      placeholder: 'https://...',
    }) as HTMLInputElement

    this.nodes.suggester = make.element('div', 'textSuggester', { style: 'display:none' })
    const options = { highlight: true, dispMax: 20, dispAllKey: true }
    new Suggest.Local(
      this.nodes.input,
      this.nodes.suggester,
      window.pagesUriList ?? [],
      options,
    )

    this.nodes.targetBlank = make.switchInput('targetBlank', this.api.i18n.t('New tab'))

    this.nodes.selectRel = this.buildSelect('None', this.availableRels)
    this.nodes.selectDesign = this.buildSelect('Text link', this.availableDesigns)

    const fields = make.element('div', 'link-options__fields')
    fields.append(
      this.nodes.targetBlank,
      this.buildField('Rel', this.nodes.selectRel),
      this.buildField('Style', this.nodes.selectDesign),
    )

    this.nodes.wrapper = make.element('div', 'link-options-wrapper')
    this.nodes.wrapper.append(this.nodes.input, this.nodes.suggester!, fields)

    this.nodes.wrapper.addEventListener('change', () => {
      this.updateLink()
    })

    this.nodes.wrapper.addEventListener('copy', async (e) => {
      await navigator.clipboard.write([
        new ClipboardItem({
          'text/html': new Blob([this.anchorTag?.outerHTML || ''], { type: 'text/html' }),
          'text/plain': new Blob([this.nodes.input!.value], { type: 'text/plain' }),
        }),
      ])
      e.preventDefault()
    })

    this.nodes.input!.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault()
        event.stopPropagation()
        event.stopImmediatePropagation()
        this.updateLink()
        this.closeActions()
      }
    })
    return this.nodes.wrapper
  }

  /**
   * A select whose blank first option names what "no value" gives, label =>
   * value. The field name itself belongs to the label, which stays on screen
   * once a value is picked — the blank option does not.
   */
  private buildSelect(
    emptyLabel: string,
    choices: Record<string, string>,
  ): HTMLSelectElement {
    const select = make.element('select', [
      this.api.styles.input,
      'link-options__select',
    ]) as HTMLSelectElement
    make.option(select, '', this.api.i18n.t(emptyLabel))

    for (const [label, value] of Object.entries(choices)) {
      make.option(select, value, this.api.i18n.t(label))
    }

    return select
  }

  /** Wraps a control in its label, which also places both in the panel grid. */
  private buildField(labelText: string, control: HTMLSelectElement): HTMLElement {
    const field = make.element('label', 'link-options__field')
    const label = make.element('span', 'link-options__label')
    label.textContent = this.api.i18n.t(labelText)
    field.append(label, control)

    return field
  }

  checkState(): boolean {
    const anchorTag = this.anchorTag || this.api.selection.findParentTag('A')

    if (!anchorTag) {
      this.showUnlink(false)
      return false
    }

    if (!anchorTag.innerText.includes(window.getSelection()?.toString() || '')) {
      this.showUnlink(true)
      return false
    }

    this.showUnlink()
    this.anchorTag = anchorTag
    this.openActions()
    this.updateActionValues(anchorTag)
    setTimeout(() => this.nodes.input!.focus(), 0)

    return true
  }

  surround(range: Range | null): void {
    if (!range) {
      this.toggleActions()
      return
    }

    if (this.inputOpened) {
      this.selection.restore()
      this.selection.removeFakeBackground()
    }

    const termWrapper = this.api.selection.findParentTag('A') || this.anchorTag

    if (termWrapper) {
      this.unlink(termWrapper)
      this.closeActions()
      return
    }

    this.anchorTag = document.createElement('A')
    this.anchorTag.appendChild(range.extractContents())
    range.insertNode(this.anchorTag)
    this.api.selection.expandToTag(this.anchorTag)
    this.selection.setFakeBackground()
    this.selection.save()
    this.openActions(true)
  }

  showUnlink(showUnlink = true): void {
    if (showUnlink) {
      this.nodes.button?.classList.add(this.api.styles.inlineToolButtonActive)
      this.nodes.button!.innerHTML = IconUnlink
      return
    }
    this.nodes.button!.innerHTML = IconLink
    this.nodes.button?.classList.remove(this.api.styles.inlineToolButtonActive)
  }

  updateActionValues(anchorTag: HTMLElement): void {
    if (!this.nodes.input) return

    const hrefAttr = anchorTag.getAttribute('href')
    //this.nodes.input.value = hrefAttr ? hrefAttr : ''
    this.nodes.input.setAttribute('value', hrefAttr ? hrefAttr : '')

    this.selectValue(this.nodes.selectRel!, anchorTag.getAttribute('rel') ?? '')
    this.selectValue(this.nodes.selectDesign!, anchorTag.getAttribute('class') ?? '')

    const targetAttr = anchorTag.getAttribute('target')
    this.nodes.targetBlank!.querySelector('input')!.checked = !!targetAttr
  }

  /**
   * A value the list does not offer — hand-written, or left by another site's
   * config — becomes an option of its own, or selecting anything else would
   * silently drop it. One at a time: the previous link's stray value has no
   * business in this link's list.
   */
  private selectValue(select: HTMLSelectElement, value: string): void {
    select.querySelector('option[data-unlisted]')?.remove()

    const offered = Array.from(select.options).some((option) => option.value === value)
    if ('' !== value && !offered) {
      make.option(select, value, null, { 'data-unlisted': '' })
    }

    select.value = value
  }

  get shortcut(): string {
    return 'CMD+K'
  }

  static get isInline(): boolean {
    return true
  }

  static get sanitize() {
    return {
      a: {
        href: true,
        target: true,
        rel: true,
        class: true,
      },
    }
  }

  clear(): void {
    if (this.anchorTag) this.anchorTag.style = ''
    this.selection.removeFakeBackground()
  }

  toggleActions(): void {
    if (!this.inputOpened) {
      this.openActions(true)
    } else {
      this.closeActions()
    }
  }

  openActions(needFocus = false): void {
    this.nodes.wrapper!.style.display = 'block'
    if (this.anchorTag) {
      this.api.selection.expandToTag(this.anchorTag)
      this.api.selection.setFakeBackground()
      this.api.selection.save()
    }
    if (needFocus) {
      this.nodes.input!.focus()
    }
    this.inputOpened = true
  }

  closeActions(): void {
    if (this.selection.isFakeBackgroundEnabled) {
      const currentSelection = new SelectionUtils()
      currentSelection.save()
      this.selection.restore()
      this.selection.removeFakeBackground()
      this.selection.collapseToEnd()
    }

    const value = this.nodes.input!.value || ''
    if (!value.trim()) this.unlink(this.anchorTag)
    this.inputOpened = false
    this.api.inlineToolbar.close()
  }

  updateLink(): HTMLElement | null {
    if (!this.anchorTag) return null

    const href = this.nodes.input!.value.trim() || ''
    this.anchorTag.setAttribute('href', href)

    const target = this.nodes.targetBlank!.querySelector('input')!.checked ? '_blank' : ''
    if (target) {
      this.anchorTag.setAttribute('target', target)
    } else {
      this.anchorTag.removeAttribute('target')
    }

    const rel = this.nodes.selectRel!.value || ''
    if (rel) {
      this.anchorTag.setAttribute('rel', rel)
    } else {
      this.anchorTag.removeAttribute('rel')
    }

    const design = this.nodes.selectDesign!.value || ''
    if (design) {
      this.anchorTag.className = design
    } else {
      this.anchorTag.removeAttribute('class')
    }

    return this.anchorTag
  }

  unlink(termWrapper: HTMLElement | null): void {
    if (!termWrapper) return
    this.api.selection.expandToTag(termWrapper)

    const sel = window.getSelection()
    if (!sel) return

    const range = sel.getRangeAt(0)
    if (!range) return

    const unwrappedContent = range.extractContents()
    if (!unwrappedContent) return

    termWrapper.parentNode?.removeChild(termWrapper)
    range.insertNode(unwrappedContent)
    sel.removeAllRanges()
    range.collapse()
    sel.addRange(range)
  }
}
