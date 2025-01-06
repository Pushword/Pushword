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

    const select = make.element('select', this.api.styles.input, {
      style: 'max-width: 100px;padding: 5px 6px;margin: auto; position: absolute; right: 5px; z-index: 5; background: white',
    })
    make.options(select, ['html', 'twig', 'javascript', 'php', 'json', 'yaml'])
    select.value = this.data.language
    select.addEventListener('change', (event) => {
      this.data.language = event.target.value
      this.editorInstance.getModel().setLanguage(this.data.language)
    })

    //wrapper.appendChild(select)

    const editorWrapper = wrapper.firstChild
    wrapper.insertBefore(select, editorWrapper)
    wrapper.style.marginBottom = '35px'
    wrapper.style.position = 'relative'
    wrapper.classList.add('monaco-codeblock-wrapper')

    return wrapper
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
      html: this.editorInstance.getValue(),
      language: this.data.language, // wrapper.querySelector('select.CodeBlock_language').value
    }

    return this.data
  }

  static get toolbox() {
    return {
      icon: Icon,
      title: 'Raw',
    }
  }
}
