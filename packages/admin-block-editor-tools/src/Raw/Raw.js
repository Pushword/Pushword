import RawTool from './../../node_modules/@editorjs/raw/src/index.js'
//import { transformTextareaToAce } from './../../../admin/src/Resources/assets/admin.ace-editor.js';
import css from './../../node_modules/@editorjs/raw/src/index.css'
import './Raw.css'

export default class Raw extends RawTool {
  // Wait for PR  https://github.com/editor-js/raw/pull/25 merged
  constructor({ data, config, api, readOnly }) {
    super({ data, config, api, readOnly })
    //this.defaultHeight = config.defaultHeight || 200;
    this.editor = null
  }

  // Wait for PR  https://github.com/editor-js/raw/pull/27 merged
  static get conversionConfig() {
    return {
      export: 'html',
      import: 'html',
    }
  }

  render() {
    let wrapper = super.render()

    this.editor = this.transformTextareaToAce()

    this.editor.on('focus', function () {
      wrapper.classList.add('ce-rawtool-focus')
    })

    this.editor.on('blur', function () {
      wrapper.classList.remove('ce-rawtool-focus')
    })

    return wrapper
  }

  /**
   * Extract Tool's data from the view
   *
   * @param {HTMLDivElement} rawToolsWrapper - RawTool's wrapper, containing textarea with raw HTML code
   * @returns {RawData} - raw HTML code
   * @public
   */
  save(rawToolsWrapper) {
    return {
      html: rawToolsWrapper.querySelector('textarea.ce-rawtool__textarea').value,
    }
  }

  transformTextareaToAce() {
    var textarea = $(this.textarea)
    var editDiv = $('<div>', {
      position: 'absolute',
      width: '100%',
      class: 'aceInsideEditorJs',
    }).insertAfter(textarea)
    textarea.css('display', 'none')
    var editor = ace.edit(editDiv[0])
    editor.renderer.setShowGutter(false)
    editor.getSession().setValue(textarea.val() || '')
    editor.getSession().setMode('ace/mode/twig')
    editor.setFontSize('16px')
    editor.container.style.lineHeight = '1.5em'
    editor.renderer.updateFontSize()
    editor.setOptions({
      maxLines: Infinity,
    })
    editor.session.setTabSize(2)

    editor.getSession().on('change', () => {
      if (textarea) textarea.val(editor.getSession().getValue() || '')
    })
    editor.renderer.setScrollMargin(10, 10)
    editor.focus()
    setTimeout(function () {
      editor.focus()
    }, 1)

    return editor
  }
}
