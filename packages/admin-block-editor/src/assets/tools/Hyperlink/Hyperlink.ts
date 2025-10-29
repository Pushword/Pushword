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
  hideForBot: HTMLElement | null
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

  private api: API
  private availableDesign: Record<string, string> = {
    bouton: 'link-btn',
    discret: 'ninja', //text-current no-underline border-0 font-normal
  }

  private nodes: HyperlinkNodes = {
    wrapper: null,
    input: null,
    selectDesign: null,
    hideForBot: null,
    targetBlank: null,
    button: null,
    linkButton: null,
    unlinkButton: null,
  }

  private inputOpened = false
  private anchorTag: HTMLElement | null = null
  private selection: SelectionUtils

  constructor({ api }: { api: API }) {
    this.api = api
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
    this.nodes.input = make.element('input', this.api.styles.input, {
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

    this.nodes.hideForBot = make.switchInput('hideForBot', this.api.i18n.t('Obfusquer'))
    this.nodes.targetBlank = make.switchInput(
      'targetBlank',
      this.api.i18n.t('Nouvel onglet'),
    )

    this.nodes.selectDesign = make.element(
      'select',
      this.api.styles.input,
    ) as HTMLSelectElement
    make.option(this.nodes.selectDesign, '', this.api.i18n.t('Style'), {
      style: 'opacity: 0.5',
    })
    for (const [key, value] of Object.entries(this.availableDesign)) {
      make.option(this.nodes.selectDesign, value, key)
    }

    this.nodes.wrapper = document.createElement('div')
    this.nodes.wrapper.classList.add('link-options-wrapper')
    this.nodes.wrapper.append(
      this.nodes.input,
      this.nodes.suggester!,
      this.nodes.hideForBot,
      this.nodes.targetBlank,
      this.nodes.selectDesign,
    )

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
    this.nodes.input.value = hrefAttr ? hrefAttr : ''

    const relAttr = anchorTag.getAttribute('rel')
    this.nodes.hideForBot!.querySelector('input')!.checked = !!relAttr

    const targetAttr = anchorTag.getAttribute('target')
    this.nodes.targetBlank!.querySelector('input')!.checked = !!targetAttr

    const designAttr = anchorTag.getAttribute('class')
    this.nodes.selectDesign!.value = designAttr ? designAttr : ''
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

    const rel = this.nodes.hideForBot!.querySelector('input')!.checked ? 'obfuscate' : ''
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
