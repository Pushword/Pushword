import css from './index.css'
import make from './../Abstract/make.js'
import Uploader from '@vietlongn/editorjs-carousel/src/uploader'
import { IconPicture } from '@codexteam/icons'

export default class Image {
  static get toolbox() {
    return {
      title: 'Image',
      icon: IconPicture,
    }
  }

  /**
   * CSS classes
   */
  get CSS() {
    return {
      baseClass: this.api.styles.block,
      loading: this.api.styles.loader,
      input: this.api.styles.input,
      button: this.api.styles.button,

      /**
       * Tool's classes
       */
      wrapper: 'image-tool',
      imageContainer: 'image-tool__image',
      imageEl: 'image-tool__image-picture',
      imagePreloader: 'image-tool__image-preloader',
      caption: 'image-tool__caption',
    }
  }

  /**
   * Ui statuses:
   * - empty
   * - uploading
   * - filled
   */
  static get status() {
    return {
      EMPTY: 'empty',
      UPLOADING: 'loading',
      FILLED: 'filled',
    }
  }

  constructor({ data, config, api, readOnly }) {
    this.api = api
    this.config = config
    this.readOnly = readOnly
    this.data = data || {}

    this.onSelectFile = config.onSelectFile || this.defaultOnSelectFile
    this.onUploadFile = config.onUploadFile || this.defaultOnUploadFile
    this.nodes = {}
    this.imageUrl = null
    this.caption = ''

    // Configuration de l'uploader (utilise l'uploader du carousel)
    this.uploader = new Uploader({
      config: {
        endpoints: config.endpoints || '',
        additionalRequestData: config.additionalRequestData || {},
        additionalRequestHeaders: config.additionalRequestHeaders || {},
        field: config.field || 'image',
        types: config.types || 'image/*',
        captionPlaceholder: this.api.i18n.t('Caption'),
        buttonContent: config.buttonContent || '',
        uploader: config.uploader || undefined,
      },
      onUpload: (response) => this.onUpload(response),
      onError: (error) => this.uploadingFailed(error),
    })
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

  buildFullUrl(mediaNameOrUrl) {
    // Construit l'URL complète à partir du nom du média
    if (this.isFullUrl(mediaNameOrUrl)) {
      // C'est déjà une URL complète (rétrocompatibilité)
      return mediaNameOrUrl
    }
    // C'est un nom de média, construire l'URL
    return `/media/md/${mediaNameOrUrl}`
  }

  defaultOnSelectFile() {
    this.uploader.uploadSelectedFile({
      onPreview: (src) => {
        this.showPreloader(src)
      },
    })
  }

  defaultOnUploadFile() {
    // Utilise la même méthode que defaultOnSelectFile pour ouvrir la popup de sélection
    // Cette méthode peut être surchargée par la configuration
    this.uploader.uploadSelectedFile({
      onPreview: (src) => {
        this.showPreloader(src)
      },
    })
  }

  /**
   * Toggle tool's status
   */
  toggleStatus(status) {
    for (const statusType in Image.status) {
      if (Object.prototype.hasOwnProperty.call(Image.status, statusType)) {
        this.nodes.wrapper.classList.toggle(`${this.CSS.wrapper}--${Image.status[statusType]}`, status === Image.status[statusType])
      }
    }
  }

  showPreloader(src) {
    if (this.nodes.imagePreloader && src) {
      this.nodes.imagePreloader.style.backgroundImage = `url(${src})`
    }

    this.toggleStatus(Image.status.UPLOADING)
  }

  hidePreloader() {
    if (this.nodes.imagePreloader) {
      this.nodes.imagePreloader.style.backgroundImage = ''
    }
    this.toggleStatus(Image.status.EMPTY)
  }

  onUpload(response) {
    if (response.success && response.file) {
      this.fillImage(response.file.url)
      // fillImage() gère déjà le passage à l'état FILLED et cache le preloader

      // Stocker les données
      this.imageUrl = response.file.url

      // Récupérer le caption depuis le nom du fichier
      if (response.file.name) {
        this.caption = response.file.name
        this.fillCaption(this.caption)
      }
    } else {
      this.uploadingFailed('incorrect response: ' + JSON.stringify(response))
    }
  }

  uploadingFailed(errorText) {
    console.log('Image: uploading failed because of', errorText)
    this.hidePreloader()

    // Remettre l'interface en état initial (afficher le bouton, cacher le container)
    this.showFileButton()

    this.api.notifier.show({
      message: this.api.i18n.t("Échec du téléchargement de l'image"),
      style: 'error',
    })
  }

  fillImage(url) {
    if (this.nodes.imageContainer) {
      // Supprimer l'image existante si elle existe
      if (this.nodes.imageEl) {
        this.nodes.imageEl.remove()
      }

      const img = make.element('img', this.CSS.imageEl)
      img.src = url

      // Attendre que l'image soit chargée pour passer en état FILLED
      img.addEventListener('load', () => {
        this.toggleStatus(Image.status.FILLED)

        // Cacher le preloader si il existe
        if (this.nodes.imagePreloader) {
          this.nodes.imagePreloader.style.backgroundImage = ''
        }
      })

      this.nodes.imageEl = img
      this.nodes.imageContainer.appendChild(img)
    }
  }

  fillCaption(text) {
    if (this.nodes.caption) {
      this.nodes.caption.textContent = text || ''
    }
  }

  showFileButton() {
    this.toggleStatus(Image.status.EMPTY)
  }

  createImageInput() {
    // Créer les nœuds comme dans AbstractUi
    this.nodes = {
      wrapper: make.element('div', [this.CSS.baseClass, this.CSS.wrapper]),
      imageContainer: make.element('div', [this.CSS.imageContainer]),
      fileButton: this.createFileButton(),
      imageEl: undefined,
      imagePreloader: make.element('div', this.CSS.imagePreloader),
      caption: make.element('div', [this.CSS.input, this.CSS.caption], {
        contentEditable: !this.readOnly,
      }),
    }

    /**
     * Create base structure comme dans AbstractUi
     *  <wrapper>
     *    <image-container>
     *      <image-preloader />
     *    </image-container>
     *    <caption />
     *    <select-file-button />
     *  </wrapper>
     */
    this.nodes.caption.dataset.placeholder = this.config.captionPlaceholder || this.api.i18n.t('Caption')
    this.nodes.imageContainer.appendChild(this.nodes.imagePreloader)
    this.nodes.wrapper.appendChild(this.nodes.imageContainer)
    this.nodes.wrapper.appendChild(this.nodes.caption)
    this.nodes.wrapper.appendChild(this.nodes.fileButton)

    return this.nodes.wrapper
  }

  createFileButton() {
    try {
      return make.fileButtons(this, ['cdx-input-gallery'])
    } catch (error) {
      console.warn('Erreur lors de la création du bouton fichier:', error)
      // Fallback: créer un bouton simple
      const button = make.element('div', [this.CSS.button])
      button.textContent = this.api.i18n.t('Select an Image')
      button.addEventListener('click', () => this.defaultOnSelectFile())
      return button
    }
  }

  render() {
    const wrapper = this.createImageInput()

    // Déterminer l'état initial basé sur les données comme dans AbstractUi
    if (!this.data.media && (!this.data.file || Object.keys(this.data.file || {}).length === 0)) {
      this.toggleStatus(Image.status.EMPTY)
    } else {
      // On a des données d'image, charger l'image
      let url = ''

      if (this.data.media) {
        url = this.buildFullUrl(this.data.media)
      } else if (this.data.file) {
        if (typeof this.data.file === 'string') {
          url = this.buildFullUrl(this.data.file)
        } else if (this.data.file.url) {
          url = this.data.file.url
        }
      }

      if (url) {
        this.fillImage(url)
        this.imageUrl = url

        // Charger le caption
        if (this.data.caption) {
          this.caption = this.data.caption
          this.fillCaption(this.caption)
        }
      } else {
        this.toggleStatus(Image.status.EMPTY)
      }
    }

    return wrapper
  }

  save() {
    // Extraire le nom du média et le caption
    if (this.imageUrl) {
      const mediaName = this.extractMediaName(this.imageUrl)

      // Récupérer le caption depuis le champ
      let caption = ''
      if (this.nodes.caption) {
        caption = this.nodes.caption.textContent.trim()
      }

      return {
        media: mediaName,
        caption: caption,
      }
    }

    return {}
  }

  validate() {
    return !!this.imageUrl
  }

  static get pasteConfig() {
    return {
      tags: ['img'],
      patterns: {
        image: /(https?:\/\/|\/media\/)\S+\.(gif|jpe?g|png|webp)$/i,
      },
      files: {
        mimeTypes: ['image/*'],
      },
    }
  }

  onPaste(event) {
    switch (event.type) {
      case 'tag': {
        const img = event.detail.data
        const url = img.src

        if (url) {
          this.fillImage(url)
          this.imageUrl = url
        }
        break
      }

      case 'pattern': {
        const url = event.detail.data

        if (url) {
          this.fillImage(url)
          this.imageUrl = url
        }
        break
      }

      case 'file': {
        const file = event.detail.file

        this.uploader.uploadSelectedFile({
          onPreview: (src) => {
            this.showPreloader(src)
          },
        })
        break
      }
    }
  }
}
