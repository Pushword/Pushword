import codecup from '@calumk/codecup/dist/codecup.bundle.js'
//import codecup from '@calumk/codecup/src/codecup.js'
import Icon from './icon.svg'
import css from './../../node_modules/@editorjs/raw/src/index.css'
import './Raw.css'
import Prism from 'prismjs'
import 'prismjs/components/prism-markup.js'
import 'prismjs/components/prism-markup-templating.js'
import 'prismjs/components/prism-twig.js'
window.Prism = Prism

export default class Raw {
  static get enableLineBreaks() {
    return true
  }

  constructor({ api, data }) {
    this.api = api

    this._CSS = {
      block: this.api.styles.block,
      wrapper: 'ce-EditorJsCodeCup',
      settingsButton: this.api.styles.settingsButton,
      settingsButtonActive: this.api.styles.settingsButtonActive,
    }

    this.onKeyUp = this.onKeyUp.bind(this)

    this._element // used to hold the wrapper div, as a point of reference

    this.editorInstance = {}
    this.html = data.html === undefined ? '' : data.html
  }

  /**
   * @param {KeyboardEvent} e - key up event
   */
  onKeyUp(e) {
    if (e.code !== 'Backspace' && e.code !== 'Delete') {
      return
    }

    const { textContent } = this._element

    if (textContent === '') {
      this._element.innerHTML = ''
    }
  }

  render() {
    this._element = document.createElement('div')
    this._element.classList.add('editorjs-codeCup_Wrapper')
    let editorElem = document.createElement('div')
    editorElem.classList.add('editorjs-codeCup_Editor')
    editorElem.innerHTML = this.html
    this._element.appendChild(editorElem)

    /** @var Prism */
    // const CodeCupPrism = codecup.prism()
    // CodeCupPrism.language = Prism.languages
    this.editorInstance = new codecup(editorElem, {
      language: 'html',
      lineNumbers: false,
      copyButton: true,
    })
    this.editorInstance.onUpdate((code) => {
      let _length = code.split('\n').length
      this._debounce(this._updateEditorHeight(_length))
    })

    //this.editorInstance.updateCode(this.html)

    return this._element
  }

  _updateEditorHeight(length) {
    let _height = length * 21 + 10
    if (_height < 60) {
      _height = 60
    }

    this._element.style.height = _height + 'px'
  }

  _debounce(func, timeout = 500) {
    let timer
    return (...args) => {
      clearTimeout(timer)
      timer = setTimeout(() => {
        func.apply(this, args)
      }, timeout)
    }
  }

  save() {
    this.html = this.editorInstance.getCode()
    return {
      html: this.html,
    }
  }

  static get isReadOnlySupported() {
    return true
  }

  static get toolbox() {
    return {
      icon: Icon,
      title: 'Raw',
    }
  }
}
