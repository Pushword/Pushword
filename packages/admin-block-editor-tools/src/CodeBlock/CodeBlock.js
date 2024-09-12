import RawTool from './../../node_modules/@editorjs/raw/src/index.js'
import Icon from './icon.svg'
import make from './../Abstract/make.js'
import Raw from './../Raw/Raw.js'

/**
 * The code is contains in html, but it could be whatever you want
 */
export default class CodeBlock extends Raw {
  constructor({ data, config, api, readOnly }) {
    super({ data, config, api, readOnly })
    this.data = {
      html: data.html || '',
      language: data.language || 'html',
    }
  }

  render() {
    const wrapper = super.render()

    const select = make.element('select', 'CodeBlock_language')
    make.options(select, ['html', 'javascript', 'php'])
    select.value = this.data.language
    select.addEventListener('change', (event) => {
      this.editor.getSession().setMode('ace/mode/' + event.target.value)
      this.data.language = event.target.value
    })

    wrapper.appendChild(select)

    return wrapper
  }

  transformTextareaToAce() {
    this.editor = super.transformTextareaToAce()
    this.editor.renderer.setShowGutter(true)
    return this.editor
  }
  /**
   * Extract Tool's data from the view
   *
   * @param {HTMLDivElement} wrapper - RawTool's wrapper, containing textarea with raw HTML code
   * @returns {RawData} - raw HTML code
   * @public
   */
  save(wrapper) {
    this.data = {
      html: wrapper.querySelector('textarea.ce-rawtool__textarea').value,
      language: this.data.language, // wrapper.querySelector('select.CodeBlock_language').value
    }

    console.log(this.data)

    return this.data
  }

  static get toolbox() {
    return {
      icon: Icon,
      title: 'Raw',
    }
  }
}
