import css from './HyperlinkTune.css'
import make from '../Abstract/make.js'
import Hyperlink from '../Hyperlink/Hyperlink.js'
// todo get selection utils https://github.com/codex-team/editor.js/blob/main/src/components/selection.ts
// and drop editorjs-hyperlink dependency

// TODO WiP
export default class HyperlinkTune {
  static get isTune() {
    return true
  }

  constructor({ api, data }) {
    this.api = api
    this.data = data || { url: '', hideForBot: true, targetBlank: false }

    this._CSS = {
      classWrapper: 'cdx-anchor-tune-wrapper',
      classIcon: 'cdx-anchor-tune-icon',
      classInput: 'cdx-anchor-tune-input',
    }

    this.nodes = {}
    this.i18n = api.i18n
  }

  /**
   * Rendering tune wrapper
   * @returns {*}
   */
  render(value = null) {
    const wrapper = document.createElement('div')
    //wrapper.classList.add(" ");

    const wrapperIcon = document.createElement('div')
    wrapperIcon.classList.add(this._CSS.classIcon)
    wrapperIcon.appendChild(Hyperlink.iconSvg('link', 12, 12))

    this.nodes.url = make.input(this, ['cdx-input-labeled', 'cdx-input-full'], '<a href=#>image</a>', this.data.url)
    this.nodes.hideForBot = make.switchInput('hideForBot', this.i18n.t('Obfusquer'))
    this.nodes.targetBlank = make.switchInput('targetBlank', this.i18n.t('Nouvel onglet'))

    wrapper.appendChild(wrapperIcon)
    wrapper.appendChild(this.nodes.url)
    wrapper.appendChild(this.nodes.hideForBot)
    wrapper.appendChild(this.nodes.targetBlank)

    return wrapper
  }
  /**
   * Save
   * @returns {*}
   */
  save() {
    return this.data
  }
}
