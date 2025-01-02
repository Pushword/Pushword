import Icon from './icon.svg'
//import css from './../../node_modules/@editorjs/raw/src/index.css'
import './Raw-monaco.css'

/**
 * @typedef {import('monaco-editor').editor.IStandaloneCodeEditor} MonacoEditor
 */

export default class Raw {
  static get enableLineBreaks() {
    return true
  }

  constructor({ api, data }) {
    this.api = api
    this.wrapper = null
    this.editorInstance = {}
    this.html = data.html === undefined ? '' : data.html
  }

  render() {
    this.wrapper = document.createElement('div')
    this.wrapper.classList.add('editorjs-monaco-wrapper')

    // Create Monaco editor container
    let editorElem = document.createElement('div')
    editorElem.classList.add('editorjs-monaco-editor')
    editorElem.style.height = '100%' // Default height
    this.wrapper.appendChild(editorElem)

    // Initialize Monaco editor
    /** @type {typeof import('monaco-editor')} */
    const monaco = window.monaco
    /** @type {import('./../../../admin-monaco-editor/MonacoHelper.js').default} */
    const monacoHelper = window.monacoHelper
    this.editorInstance = monaco.editor.create(editorElem, { value: this.html, language: 'twig', ...monacoHelper.defaultSettings })
    const monacoHelperInstance = new monacoHelper(this.editorInstance)

    monacoHelperInstance.updateHeight(this.wrapper)
    this.editorInstance.onDidChangeModelContent(() => {
      monacoHelperInstance.updateHeight(this.wrapper)
      monacoHelperInstance.autocloseTag()
    })

    return this.wrapper
  }

  // debounce(func, timeout = 500) {
  //   let timer
  //   return (...args) => {
  //     clearTimeout(timer)
  //     timer = setTimeout(() => func(...args), timeout)
  //   }
  // }

  save() {
    this.html = this.editorInstance.getValue()
    return { html: this.html }
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
