import css from './index.css'
import Uploader from './../../node_modules/@editorjs/image/src/uploader.js'
import Ui from './../../node_modules/@editorjs/image/src/ui.js'
import ToolboxIcon from './toolbox-icon.svg'
import Abstract from './../Abstract/Abstract.js'
import make from './../Abstract/make.js'

export default class Embed extends Abstract {
  static get toolbox() {
    return { title: 'Embed', icon: ToolboxIcon }
  }

  constructor({ data, config, api, readOnly }) {
    super({ data, config, api, readOnly })

    this.onSelectFile = config.onSelectFile || this.defaultOnSelectFile
    this.onUploadFile = config.onUploadFile || ''
    this.uploader = new Uploader({
      config: this.config,
      onUpload: function (response) {
        return this.onUpload(response)
      },
      onError: function (response) {
        return this.uploadingFailed(response)
      },
    })
  }

  get defaultOnSelectFile() {
    this.uploader.uploadSelectedFile({
      onPreview: function (src) {
        this.showPreloader(src)
      },
    })
  }

  onUpload(response) {
    console.log(response)
    if (response.success && response.file) {
      this._data.image = response.file
      if (this._data.image.url) this.fillImage(this._data.image.url)
      else this.uploadingFailed('incorrect response: ' + JSON.stringify(response))
    }
  }

  uploadingFailed(error) {
    console.log('Image Tool: uploading failed because of', error),
      this.api.notifier.show({
        message: this.api.i18n.t('Couldn’t upload image. Please try another.'),
        style: 'error',
      }),
      this.hidePreloader()
  }

  createInputs() {
    this.nodes.inputAlternativeText = make.input(
      this,
      ['cdx-input-labeled', 'cdx-input-labeled-embed-text', ...this.CSS.inputClass],

      'Alternative Text',
      this._data.alternativeText,
    )

    this.nodes.inputServiceUrl = make.input(this, ['cdx-input-labeled', 'cdx-input-labeled-embed-service-url', ...this.CSS.inputClass], 'Service URL', this._data.serviceUrl)
    this.createImageInput()

    const wrapper = make.element('div')

    wrapper.appendChild(this.nodes.inputAlternativeText)
    wrapper.appendChild(this.nodes.inputServiceUrl)
    wrapper.appendChild(this.nodes.fileButton)

    return wrapper
  }

  createImageInput() {
    this.nodes.imagePreloader = make.element('div', 'image-tool__image-preloader')
    this.nodes.imagePreloader.style.display = 'none'
    this.nodes.fileButton = make.fileButtons(this)
    this.nodes.fileButton.appendChild(this.nodes.imagePreloader)
    if (this._data.image) this.fillImage(this._data.image.url)
  }

  show(state) {
    if (state === this.STATE.VIEW) {
      if (this.validate()) {
        this.nodes.preview.innerHTML =
          '<a href="' +
          this._data.serviceUrl +
          '" style="display:block;--aspect-ratio:16/9;background: center / cover no-repeat url(\'' +
          this._data.image.url +
          '\');" target=_blank><div style="display: flex;justify-content: center;align-items: center; width:100%;height:100%;color:#c4302b">' +
          ToolboxIcon.replace('width="16"', 'width="100"').replace('height="16"', 'height="100"') +
          '</div></a>'
      } else {
        this.api.notifier.show({
          message: this.api.i18n.t('Something is missing to properly render the embeded video.'),
          style: 'error',
        })
      }
    }
    super.show(state)
  }

  updateData() {
    this._data.serviceUrl = this.nodes.inputServiceUrl.textContent
    this._data.alternativeText = this.nodes.inputAlternativeText.textContent
  }

  showPreloader(src) {
    this.nodes.imagePreloader.style.display = 'block'
    this.nodes.imagePreloader.style.backgroundImage = 'url(' + src + ')'
    this.toggleStatus(Ui.status.UPLOADING)
  }

  hidePreloader() {
    this.nodes.imagePreloader.style.display = 'none'
    this.nodes.imagePreloader.style.backgroundImage = ''
    this.toggleStatus(Ui.status.EMPTY)
  }

  fillImage(src) {
    if (this.nodes.imageEl) this.nodes.imageEl.remove()

    this.nodes.imageEl = make.element('img', 'image-tool__image-picture', {
      src: src,
      style: 'max-height:47px;padding-left:1em',
    })
    this.showPreloader(src)
    const Tool = this
    this.nodes.imageEl.addEventListener('load', function () {
      Tool.toggleStatus(Ui.status.FILLED)
      if (Tool.nodes.imagePreloader) Tool.hidePreloader()
    }),
      this.nodes.fileButton.appendChild(this.nodes.imageEl)
    if (this.validate() && this.nodes.inputs) this.show(this.STATE.VIEW)
  }

  validate() {
    return !!(this._data.serviceUrl && this._data.alternativeText && this._data.image && this._data.image.url)
  }

  toggleStatus(status) {
    for (var statusType in Ui.status) {
      if (Object.prototype.hasOwnProperty.call(Ui.status, statusType))
        this.nodes.wrapper.classList.toggle(this.CSS.wrapper + '--' + Ui.status[statusType], status === Ui.status[statusType])
    }
  }
}
