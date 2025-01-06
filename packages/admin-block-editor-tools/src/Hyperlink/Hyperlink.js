import css2 from './Hyperlink.css'
import SelectionUtils from './Selection'
import make from './../Abstract/make.js'
import { IconLink, IconUnlink } from '@codexteam/icons'
import { Suggest } from '../../../admin/src/Resources/assets/suggest.js'
import { API, InlineTool, SanitizerConfig, ToolConfig, Selection } from '@editorjs/editorjs'

/**
 * TODO Test it :
 * - When clicking ctrl+k, then pressing ENTER, no link is created and the toolbar is closed, the cursor is setted at the end of the previously selected text
 * - When clicking ctrl+k, pasting value OR typing value, then pressing ENTER, the link is created and the toolbar is closed, the cursor is setted at the end of the previously selected text
 * - When clicking on an existing link, changing the value, the value is automatically updated
 * - When clicking on an existing link, changing the value, then clicking another area, the toolbar is closed and the value is updated
 * - When deleting link, link is deleted and replaced by the innerText, toolbar is closed, the cursor is setted at the end of the innerText
 * - when selecting a part of a link, it's expanding to the whole link
 * - when selection a part of a link + other content, it's do nothing <- not working
 *
 * @implements {InlineTool}
 */
export default class Hyperlink {
  /** @returns {boolean} */
  static isInline = true

  /** Title for hover-tooltip */
  static title = 'Link'

  /** @type {API} */
  api
  /** @type {{ [key: string]: string }} */
  availableDesign = {
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
  nodes = {
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
  inputOpened = false

  /** @type {HTMLElement | null} */
  anchorTag = null

  /** @type {SelectionUtils} */
  selection

  /** @param {{ api: API }} options  */
  constructor({ api }) {
    this.api = api
    this.selection = new SelectionUtils()
  }

  /** @returns {HTMLElement} */
  render() {
    console.log('render')
    this.nodes.button = document.createElement('button')
    this.nodes.button.type = 'button'
    this.nodes.button.classList.add(this.api.styles.inlineToolButton)
    this.nodes.button.innerHTML = IconLink
    return this.nodes.button
  }

  renderActions() {
    console.log('renderActions', this.nodes.input)
    //if (this.nodes.input) return this.nodes.wrapper

    this.nodes.input = make.element('input', this.api.styles.input, { placeholder: 'https://...' })

    this.nodes.suggester = make.element('div', 'textSuggester', { style: 'display:none' })
    const options = { highlight: true, dispMax: 20, dispAllKey: true }
    new Suggest.Local(this.nodes.input, this.nodes.suggester, window.pagesUriList ?? [], options)

    this.nodes.hideForBot = make.switchInput('hideForBot', this.api.i18n.t('Obfusquer'))
    this.nodes.targetBlank = make.switchInput('targetBlank', this.api.i18n.t('Nouvel onglet'))

    this.nodes.selectDesign = make.element('select', this.api.styles.input)
    make.option(this.nodes.selectDesign, '', this.api.i18n.t('Style'), { style: 'opacity: 0.5' })
    for (const [key, value] of Object.entries(this.availableDesign)) {
      make.option(this.nodes.selectDesign, value, key)
    }

    this.nodes.wrapper = document.createElement('div')
    this.nodes.wrapper.classList.add('link-options-wrapper')
    this.nodes.wrapper.append(this.nodes.input, this.nodes.suggester, this.nodes.hideForBot, this.nodes.targetBlank, this.nodes.selectDesign)

    // sauvegarde lors d'un changement
    this.nodes.wrapper.addEventListener('change', (event) => {
      this.updateLink()
    })

    // Permit copy the link even if we focus the input
    this.nodes.wrapper.addEventListener('copy', async (e) => {
      console.log('copy', this.anchorTag)
      await navigator.clipboard.write([
        new ClipboardItem({
          'text/html': new Blob([this.anchorTag?.outerHTML], { type: 'text/html' }),
          'text/plain': new Blob([this.nodes.input.value], { type: 'text/plain' }),
        }),
      ])
      e.preventDefault() // Prevent the default copy action
    })

    // sauvegarde lors de ENTER
    this.nodes.input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        console.log('press ENTER')
        //this.enterPressed(event)
        event.preventDefault()
        event.stopPropagation()
        event.stopImmediatePropagation()
        this.updateLink()
        this.closeActions()
      }
    })

    console.log('/renderActions')
    return this.nodes.wrapper
  }

  checkState() {
    console.log('checkState')

    const anchorTag = this.anchorTag || this.api.selection.findParentTag('A') // this.anchorTag ||

    if (!anchorTag) {
      this.showUnlink(false)
      return false
    }

    if (!anchorTag.innerText.includes(window.getSelection().toString())) {
      this.showUnlink(true)
      return false
    }

    this.showUnlink()
    this.anchorTag = anchorTag
    this.openActions()
    this.updateActionValues(anchorTag)
    // This is a hack to avoid infinite loop of rebuilding this inline tool
    // cons ➜ this is not possible for the user to copy or paste a text wich start or end by a link
    setTimeout(() => this.nodes.input.focus(), 0)

    console.log('/checkState')
    return true
  }

  /** @param {Range} range */
  surround(range) {
    console.log('surround', range, this.anchorTag)
    // Range will be null when user makes second click on the 'link icon' to close opened input */
    if (!range) {
      this.toggleActions()
      return
    }

    if (this.inputOpened) {
      this.selection.restore()
      this.selection.removeFakeBackground()
    }

    const termWrapper = this.api.selection.findParentTag('A') || this.anchorTag
    console.log(termWrapper, this.inputOpened)
    if (termWrapper) {
      // && this.inputOpened
      this.unlink(termWrapper)
      this.closeActions()
      //this.checkState()
      return
    }
    //else if (termWrapper) {
    //   this.showUnlink(false)
    //   console.log(this.anchorTag)
    //   this.updateActionValues()
    //   //this.api.selection.expandToTag(this.anchorTag)
    //   this.openActions(true)
    // } else {
    console.log('create A')
    this.anchorTag = document.createElement('A')
    this.anchorTag.appendChild(range.extractContents())
    range.insertNode(this.anchorTag)
    this.api.selection.expandToTag(this.anchorTag)
    this.selection.setFakeBackground()
    this.selection.save()
    this.openActions(true)
  }

  showUnlink(showUnlink = true) {
    if (showUnlink) {
      this.nodes.button?.classList.add(this.api.styles.inlineToolButtonActive)
      this.nodes.button.innerHTML = IconUnlink
      return
    }
    this.nodes.button.innerHTML = IconLink
    this.nodes.button?.classList.remove(this.api.styles.inlineToolButtonActive)
  }

  /**
   * @param {HTMLElement} anchorTag
   */
  updateActionValues(anchorTag) {
    console.log('updateActionValues')
    if (!this.nodes.input) return

    const hrefAttr = anchorTag.getAttribute('href')
    this.nodes.input.value = !!hrefAttr ? hrefAttr : ''

    const relAttr = anchorTag.getAttribute('rel')
    this.nodes.hideForBot.querySelector('input').checked = !!relAttr ? true : false

    const targetAttr = anchorTag.getAttribute('target')
    this.nodes.targetBlank.querySelector('input').checked = !!targetAttr ? true : false

    const designAttr = anchorTag.getAttribute('class')
    this.nodes.selectDesign.value = designAttr ? designAttr : ''

    console.log('/updateActionValues')
  }

  get shortcut() {
    return 'CMD+K'
  }

  static get isInline() {
    return true
  }

  /**
   * @returns {SanitizerConfig}
   */
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

  clear() {
    console.log('CLEAR')

    //this.selection.restore()
    if (this.anchorTag) this.anchorTag.style = ''
    this.selection.removeFakeBackground()
    //this.api.selection.restore()
    //this.api.selection.removeFakeBackground()

    //this.closeActions()
    //throw new Error()
    //if (this.inputOpened) this.closeActions()
  }

  toggleActions() {
    console.log('toggleActions', this.inputOpened)
    if (!this.inputOpened) {
      this.openActions(true)
    } else {
      this.closeActions()
    }
  }

  openActions(needFocus = false) {
    console.log('openActions')
    this.nodes.wrapper.style.display = 'block'
    if (this.anchorTag) {
      this.api.selection.expandToTag(this.anchorTag)
      this.api.selection.setFakeBackground()
      this.api.selection.save()
    }
    if (needFocus) {
      // if (this.anchorTag) this.api.selection.expandToTag(this.anchorTag)
      // this.api.selection.setFakeBackground()
      // this.api.selection.save()
      this.nodes.input.focus()
    }
    this.inputOpened = true
  }

  closeActions() {
    console.log('closeActions', this.selection.isFakeBackgroundEnabled)
    if (this.selection.isFakeBackgroundEnabled) {
      // if actions is broken by other selection We need to save new selection
      const currentSelection = new SelectionUtils()
      currentSelection.save()
      this.selection.restore()
      this.selection.removeFakeBackground()
      // and recover new selection after removing fake background
      //currentSelection.restore()
      this.selection.collapseToEnd()
    }

    let value = this.nodes.input.value || ''
    if (!value.trim()) this.unlink(this.anchorTag)
    //this.api.selection.restore()
    //this.api.selection.removeFakeBackground()
    this.inputOpened = false
    this.api.inlineToolbar.close()
    //this.api.toolbar.close()
  }

  updateLink() {
    let href = this.nodes.input.value.trim() || ''
    this.anchorTag.setAttribute('href', href)

    let target = this.nodes.targetBlank.querySelector('input').checked ? '_blank' : ''
    if (!!target) {
      this.anchorTag.setAttribute('target', target)
    } else {
      this.anchorTag.removeAttribute('target')
    }

    let rel = this.nodes.hideForBot.querySelector('input').checked ? 'obfuscate' : ''
    if (!!rel) {
      this.anchorTag.setAttribute('rel', rel)
    } else {
      this.anchorTag.removeAttribute('rel')
    }

    let design = this.nodes.selectDesign.value || ''
    if (!!design) {
      this.anchorTag.className = design
    } else {
      this.anchorTag.removeAttribute('class')
    }

    return this.anchorTag
  }

  unlink(termWrapper) {
    console.log('- unlink', termWrapper, this.anchorTag)
    termWrapper = termWrapper

    if (!termWrapper) return
    this.api.selection.expandToTag(termWrapper)

    const sel = window.getSelection()
    if (!sel) return

    const range = sel.getRangeAt(0)
    if (!range) return

    const unwrappedContent = range.extractContents()
    if (!unwrappedContent) return

    // Remove empty term-tag
    termWrapper.parentNode?.removeChild(termWrapper)
    range.insertNode(unwrappedContent)
    sel.removeAllRanges()
    range.collapse()
    sel.addRange(range)

    //this.api.selection.restore()
    // this.selection.restore()
    //this.selection.collapseToEnd()
    console.log('removed')
  }
}
