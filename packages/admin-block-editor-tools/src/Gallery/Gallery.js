//require("./index.css").toString(); /
import css from './index.css'

import CarouselTool from '@vietlongn/editorjs-carousel/src/index.js'
import make from './../Abstract/make.js'
import ToolboxIcon from './toolbox-icon.svg'

export default class Gallery extends CarouselTool {
  static get toolbox() {
    return {
      title: 'Gallery',
      icon: ToolboxIcon,
    }
  }

  constructor({ data, config, api, readOnly }) {
    super({ data, config, api, readOnly })

    this.onSelectFile = config.onSelectFile || this.defaultOnSelectFile
    this.onUploadFile = config.onUploadFile || null
    this.nodes = {}

    this.IconClose =
      '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/></svg>'
  }

  createAddButton() {
    return this.createImageInput()
  }

  createImageInput() {
    this.nodes.imagePreloader = make.element('div', 'image-tool__image-preloader')
    this.nodes.imagePreloader.style.display = 'none'
    this.nodes.fileButton = make.fileButtons(this, ['cdx-input-gallery'])
    this.nodes.fileButton.appendChild(this.nodes.imagePreloader)
    return this.nodes.fileButton
  }

  onUpload(response) {
    super.onUpload(response)
    this.list.childNodes[this.list.childNodes.length - 2].firstChild.firstChild.dataset.file = JSON.stringify(response.file)
    this.list.childNodes[this.list.childNodes.length - 2].firstChild.lastChild.value = response.file.name
  }

  render() {
    super.render()
    if (this.data.length > 0) {
      for (const load of this.data) {
        this.list.querySelectorAll('.cdxcarousel-inputUrl').forEach(function (item) {
          item.dataset.file = JSON.stringify(load.file)
        })
      }
    }

    return this.wrapper
  }

  // required to refactor the parent to have a data object instead of an array

  //   getClickableIcon() {
  //     let clickable = this.data.clickable ?? true
  //     return clickable ? '🔗' : '⛓️‍💥'
  //   }

  //   renderSettings() {
  //     console.log('renderSettings')
  //     const wrapper = document.createElement('div')

  //     const button = document.createElement('div')
  //     button.classList.add('cdx-settings-button')
  //     button.innerHTML = this.getClickableIcon()
  //     button.classList.toggle('cdx-settings-button--active', this.data.clickable ?? true)
  //     wrapper.appendChild(button)
  //     button.addEventListener('click', () => {
  //       this.data.clickable = !(this.data.clickable ?? true)
  //       button.innerHTML = this.getClickableIcon()
  //       button.classList.toggle('cdx-settings-button--active', this.data.clickable)
  //       console.log(this.data)
  //     })

  //     return wrapper
  //   }

  isJson(string) {
    try {
      JSON.parse(string)
    } catch (e) {
      return false
    }
    return true
  }

  save(blockContent) {
    const list = blockContent.getElementsByClassName(this.CSS.item)
    const data = []

    if (list.length > 0) {
      for (const item of list) {
        if (item.firstChild.value) {
          data.push({
            file: item.firstChild.dataset.file && this.isJson(item.firstChild.dataset.file) ? JSON.parse(item.firstChild.dataset.file) : {},
            url: item.firstChild.value,
            caption: item.lastChild.value,
          })
        }
      }
    }

    // data.push({
    //   clickable: this.data.clickable ?? true,
    // })
    return data
  }

  onFileLoading() {
    const newItem = this.creteNewItem('', '')
    this.list.insertBefore(newItem, this.addButton)
  }
}
