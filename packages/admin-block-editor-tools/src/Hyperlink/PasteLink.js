import { API, ToolConfig } from '@editorjs/editorjs'
import Selection from './Selection'

export default class PasteLink {
  constructor({ configuration }) {
    this.selection = new Selection()
    this.holder = typeof configuration.holder === 'string' ? document.getElementById(configuration.holder) : configuration.holder

    this.initializePasteListener()
  }

  initializePasteListener() {
    document.addEventListener(
      'paste',
      (event) => {
        let selectedNode = window.getSelection().anchorNode || window.getSelection().focusNode
        if (selectedNode && selectedNode.nodeType === Node.TEXT_NODE) selectedNode = selectedNode.parentNode

        // Do we have a text to create an anchor ?
        const textSelected = window.getSelection().toString()
        if (!textSelected) return true // TODO if clipboard content is url, transfrom clipboard content in link

        // Are we in editor.js instance
        if (!selectedNode.closest('.ce-block__content')) return true

        // Are we in a link ?
        // normally, it's not possible because if you select an a, it's opening the related toolbar
        const parentAnchor = this.selection.findParentTag('A')
        if (parentAnchor) return true

        // Do we have an URL in the clipboard to create a link ?
        const text = (event.clipboardData || window.clipboardData).getData('text')
        if (!this.isValidURL(text) && !this.isValidRelativeURI(text)) return true

        event.preventDefault()
        event.stopPropagation()

        document.execCommand(
          'insertHTML',
          false,
          (textSelected.startsWith(' ') ? ' ' : '') + `<a href="${text}">${textSelected.trim()}</a>` + (textSelected.endsWith(' ') ? ' ' : ''),
        )
      },
      true,
    )
  }

  /** @param {String} uri */
  isValidRelativeURI(uri) {
    const regex = /^\/[^\s]*$/
    return regex.test(uri)
  }

  /** @param {String} str */
  isValidURL(str) {
    try {
      new URL(str)
      return true
    } catch (_) {
      return false
    }
  }
}
