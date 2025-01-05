import SelectionUtils from './Selection'
import make from './../Abstract/make.js'
import { IconLink, IconUnlink } from '@codexteam/icons'
import { Suggest } from '../../../admin/src/Resources/assets/suggest.js'
import { API, InlineTool, SanitizerConfig, ToolConfig } from '@editorjs/editorjs'

export default class HyperLink implements InlineTool {
  /** @returns {boolean} */
  public static isInline = true

  /** Title for hover-tooltip */
  public static title = 'Link'

  /** @type {API} */
  private api
  /** @type {{ [key: string]: string }} */
  private availableDesign = {
    bouton: 'link-btn',
    discret: 'ninja', //text-current no-underline border-0 font-normal
  }

  /**
   * @type {
   *  wrapper: HTMLElement | null,
   *  input: HTMLInputElement | null,
   *  selectDesign: HTMLSelectElement | null,
   *  hideForBot: HTMLInputElement | null,
   *  targetBlank: HTMLInputElement | null,
   *  unlinkButton: HTMLButtonElement | null
   *  linkButton: HTMLButtonElement | null
   * }
   */
  private nodes = {
    wrapper: null,
    input: null,
    selectDesign: null,
    hideForBot: null,
    targetBlank: null,
    button: null,
    linkButton: null,
    unlinkButton: null,
  }

  /** @type {boolean} */
  private inputOpened = false

  /** @type {HTMLElement | null} */
  private anchorTag = null

  private selection: SelectionUtils

  /** @param {{ api: API }} options  */
  constructor({ api }) {
    this.api = api
    this.selection = new SelectionUtils()
  }

  /**
   * @returns {SanitizerConfig}
   */
  public static get sanitize() {
    return {
      a: {
        href: true,
        target: true,
        rel: true,
        class: true,
      },
    }
  }

  /**
   * Create button for Inline Toolbar
   */
  public render(): HTMLElement {
    this.nodes.button = document.createElement('button') as HTMLButtonElement
    this.nodes.button.type = 'button'
    this.nodes.button.classList.add(this.api.styles.inlineToolButton)
    this.nodes.button.innerHTML = IconLink
    return this.nodes.button
  }

  static renderActionsDone = false
  /**
   * Input for the link
   */
  public renderActions(): HTMLElement {
    console.log('renderActions', HyperLink.renderActionsDone)
    if (HyperLink.renderActionsDone) return document.createElement('input')
    HyperLink.renderActionsDone = true

    this.nodes.input = document.createElement('input') as HTMLInputElement
    this.nodes.input.placeholder = this.api.i18n.t('Add a link')
    this.nodes.input.enterKeyHint = 'done'
    this.nodes.input.classList.add(this.api.styles.input)
    this.nodes.input.style.display = 'none'
    this.nodes.input.addEventListener('keydown', (event: KeyboardEvent) => {
      if (event.key === 'Enter') {
        this.enterPressed(event)
      }
    })

    return this.nodes.input
  }

  /**
   * Check selection and set activated state to button if there are <a> tag
   */
  public checkState(): boolean {
    const anchorTag = this.api.selection.findParentTag('A')

    if (anchorTag) {
      this.nodes.button?.classList.add(this.api.styles.inlineToolButtonActive)
      this.nodes.button.innerHTML = IconUnlink
      this.openActions()

      // Fill input value with link href
      const hrefAttr = anchorTag.getAttribute('href')
      this.nodes.input.value = hrefAttr !== 'null' ? hrefAttr : ''

      this.selection.save()
    } else {
      this.nodes.button.innerHTML = IconLink
      this.nodes.button.classList.remove(this.api.styles.inlineToolButtonActive)
    }

    return !!anchorTag
  }

  /**
   * @param {Range} range - range to wrap with link
   */
  public surround(range: Range): void {
    // Range will be null when user makes second click on the 'link icon' to close opened input */
    if (range) {
      // Save selection before change focus to the input
      if (!this.inputOpened) {
        this.selection.setFakeBackground()
        this.selection.save()
      } else {
        this.selection.restore()
        this.selection.removeFakeBackground()
      }
      const parentAnchor = this.api.selection.findParentTag('A')

      // Unlink icon pressed
      if (parentAnchor) {
        this.api.selection.expandToTag(parentAnchor)
        this.unlink()
        this.closeActions()
        this.checkState()
        this.api.toolbar.close()

        return
      }
    }

    this.toggleActions()
  }

  /**
   * Function called with Inline Toolbar closing
   */
  public clear(): void {
    this.closeActions()
  }

  /**
   * Set a shortcut
   */
  public get shortcut(): string {
    return 'CMD+K'
  }

  /**
   * Show/close link input
   */
  private toggleActions(): void {
    if (!this.inputOpened) {
      this.openActions(true)
    } else {
      this.closeActions(false)
    }
  }

  /**
   * @param {boolean} needFocus - on link creation we need to focus input. On editing - nope.
   */
  private openActions(needFocus = false): void {
    this.nodes.input.style.display = 'block'
    if (needFocus) {
      this.nodes.input.focus()
    }
    this.inputOpened = true
  }

  /**
   * Close input
   *
   * @param {boolean} clearSavedSelection — we don't need to clear saved selection
   *                                        on toggle-clicks on the icon of opened Toolbar
   */
  private closeActions(clearSavedSelection = true): void {
    if (this.selection.isFakeBackgroundEnabled) {
      // if actions is broken by other selection We need to save new selection
      const currentSelection = new SelectionUtils()

      currentSelection.save()

      this.selection.restore()
      this.selection.removeFakeBackground()

      // and recover new selection after removing fake background
      currentSelection.restore()
    }

    this.nodes.input.style.display = 'block'
    this.nodes.input.value = ''
    if (clearSavedSelection) {
      this.selection.clearSaved()
    }
    this.inputOpened = false
  }

  /**
   * Enter pressed on input
   *
   * @param {KeyboardEvent} event - enter keydown event
   */
  private enterPressed(event: KeyboardEvent): void {
    let value = this.nodes.input.value || ''

    if (!value.trim()) {
      this.selection.restore()
      this.unlink()
      event.preventDefault()
      this.closeActions()

      return
    }

    this.selection.restore()
    this.selection.removeFakeBackground()

    this.insertLink(value)

    // Preventing events that will be able to happen
    event.preventDefault()
    event.stopPropagation()
    event.stopImmediatePropagation()
    this.selection.collapseToEnd()
    this.api.inlineToolbar.close()
  }

  /**
   * Inserts <a> tag with "href"
   *
   * @param {string} link - "href" value
   */
  private insertLink(link: string): void {
    /**
     * Edit all link, not selected part
     */
    const anchorTag = this.api.selection.findParentTag('A')

    if (anchorTag) {
      this.api.selection.expandToTag(anchorTag)
    }

    document.execCommand('insertHTML', false, link)
  }

  /**
   * Removes <a> tag
   */
  private unlink(): void {
    document.execCommand('unlink')
  }
}
