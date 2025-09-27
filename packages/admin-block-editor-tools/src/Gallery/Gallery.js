//require("./index.css").toString(); /

import CarouselTool from '@vietlongn/editorjs-carousel/src/index.js'
import make from './../Abstract/make.js'
import ToolboxIcon from './toolbox-icon.svg'
import css from './index.css'

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
    // Stocker l'URL complète dans le champ pour l'affichage, la méthode save() extraira le nom
    this.list.childNodes[this.list.childNodes.length - 2].firstChild.lastChild.value = response.file.url
  }

  render() {
    // Ne pas appeler super.render() car nous devons adapter le rendu pour nos données simplifiées

    // Créer la structure de base comme dans CarouselTool
    this.wrapper = make.element('div', [this.CSS.wrapper])
    this.list = make.element('div', [this.CSS.list])
    this.addButton = this.createAddButton()

    this.list.appendChild(this.addButton)
    this.wrapper.appendChild(this.list)

    if (this.data.length > 0) {
      for (const mediaData of this.data) {
        // Gérer la rétrocompatibilité : soit URL complète (ancien format) soit nom de média (nouveau format)
        const fullUrl = this.buildFullUrl(mediaData)
        const loadItem = this.creteNewItem(fullUrl, '')
        this.list.insertBefore(loadItem, this.addButton)
      }

      // Appliquer le style de background après le rendu
      setTimeout(() => {
        const imageElement = this.wrapper.querySelectorAll('.cdxcarousel-item img')
        for (const image of imageElement) {
          const imageUrl = image.getAttribute('src')
          const imageContainer = image.parentElement
          imageContainer.style.setProperty('--bg-image-url', `url('${imageUrl}')`)
        }
      }, 100)
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

  extractMediaName(url) {
    if (!url) return ''
    // Extraire le nom de fichier de l'URL (après le dernier /)
    const urlParts = url.split('/')
    return urlParts[urlParts.length - 1]
  }

  isFullUrl(data) {
    // Détermine si la donnée est une URL complète ou juste un nom de média
    if (!data || typeof data !== 'string') return false

    // Une URL complète commence par http://, https://, ou /
    return data.startsWith('http://') || data.startsWith('https://') || data.startsWith('/') || data.includes('/')
  }

  getMediaNameFromData(dataItem) {
    // Extrait le nom du média selon le format des données
    if (typeof dataItem === 'string') {
      // Ancien format : chaîne directe (URL ou nom)
      return this.isFullUrl(dataItem) ? this.extractMediaName(dataItem) : dataItem
    } else if (dataItem && typeof dataItem === 'object' && dataItem.media) {
      // Nouveau format : objet {media: "filename"}
      return dataItem.media
    }
    return ''
  }

  buildFullUrl(dataItem) {
    // Construit l'URL complète à partir des données
    if (typeof dataItem === 'string') {
      // Ancien format : chaîne directe
      if (this.isFullUrl(dataItem)) {
        // C'est déjà une URL complète (rétrocompatibilité)
        return dataItem
      }
      // C'est un nom de média, construire l'URL
      return `/media/md/${dataItem}`
    } else if (dataItem && typeof dataItem === 'object' && dataItem.url) {
      return dataItem.url
    } else if (dataItem && typeof dataItem === 'object' && dataItem.media) {
      // Nouveau format : objet {media: "filename"}
      const mediaName = dataItem.media
      if (this.isFullUrl(mediaName)) {
        return mediaName
      }
      return `/media/md/${mediaName}`
    }
    return ''
  }

  save(blockContent) {
    const list = blockContent.getElementsByClassName(this.CSS.item)
    const data = []

    if (list.length > 0) {
      for (const item of list) {
        if (item.firstChild.value) {
          // Extraire seulement le nom du média de l'URL
          const mediaName = this.extractMediaName(item.firstChild.value)
          //data.push({ media: mediaName })
          data.push(mediaName)
        }
      }
    }

    return data
  }

  onFileLoading() {
    const newItem = this.creteNewItem('', '')
    this.list.insertBefore(newItem, this.addButton)
  }
}
