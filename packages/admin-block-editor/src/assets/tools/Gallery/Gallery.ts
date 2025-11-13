import ToolboxIcon from './toolbox-icon.svg?raw'
import './index.css'
import { MediaUtils } from '../utils/media'
import { API, BlockToolData } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import CloseIcon from './Close.svg?raw'
import MoveLeftIcon from './MoveLeft.svg?raw'
import MoveRightIcon from './MoveRight.svg?raw'
import { AbstractMediaTool, MediaToolConfig, STATUS } from '../Abstract/AbstractMediaTool'
import Raw from '../Raw/Raw'
import make from '../utils/make'
import { jsonrepair } from 'jsonrepair'

interface GalleryItem {
  caption?: string
  media: string
}

interface GalleryData extends BlockToolData {
  items: GalleryItem[]
}

interface GalleryDataToNormalize
  extends BlockToolData,
    Array<
      | string
      | GalleryItem
      | {
          caption?: string
          file?: {
            media: string
          }
          url?: string
        }
    > {}

export default class Gallery extends AbstractMediaTool {
  public data: GalleryData
  private nodeList?: HTMLElement

  static get toolbox() {
    return {
      title: 'Gallery',
      icon: ToolboxIcon,
    }
  }

  constructor({
    data,
    config,
    api,
    readOnly,
  }: {
    data: GalleryDataToNormalize | GalleryData
    config: MediaToolConfig
    api: API
    readOnly: boolean
  }) {
    super({ api, config, readOnly, data })

    this.data = Gallery.normalizeData(data)
  }

  static normalizeData(data: GalleryDataToNormalize | GalleryData): GalleryData {
    const normalizedItems: GalleryItem[] = []

    if (
      data &&
      typeof data === 'object' &&
      'items' in data &&
      Array.isArray(data.items)
    ) {
      for (const item of data.items) {
        if (typeof item !== 'object') continue
        let media =
          item.media ||
          (item.url ? MediaUtils.extractMediaName(item.url) : null) ||
          item.file?.media
        if (!media) continue
        normalizedItems.push({ media: media, caption: item.caption || '' })
      }

      return { items: normalizedItems }
    }

    if (!data || !Array.isArray(data)) {
      return { items: [] }
    }

    for (const item of data) {
      if (typeof item === 'string') {
        normalizedItems.push({ media: item, caption: '' })
      } else if (typeof item === 'object' && item !== null) {
        // Priorité: item.media > item.url > item.file?.media
        let media = null
        if ('media' in item && item.media) {
          media = item.media
        } else if ('url' in item && item.url) {
          media = MediaUtils.extractMediaName(item.url)
        } else if ('file' in item && item.file && 'media' in item.file) {
          media = item.file.media
        }

        if (media) {
          normalizedItems.push({ media: media, caption: item.caption || '' })
        }
      }
    }

    return { items: normalizedItems }
  }

  onUpload(response: any): void {
    if (!this.responsIsValid(response)) {
      return this.handleUploadError('incorrect response: ' + JSON.stringify(response))
    }

    const mediaName =
      response.file.media || MediaUtils.extractMediaName(response.file.url)

    // Vérifier si le média existe déjà dans la galerie
    if (this.isMediaAlreadyInGallery(mediaName)) {
      this.handleDuplicateMediaError()
      return
    }

    const itemElement = this.getLastGalleryItem()

    this._createImage(response.file.url, itemElement, response.file.name || '')
    this.data.items.push({
      media: mediaName,
      caption: response.file.name || '',
    })

    itemElement.classList.add('cdxcarousel-item--empty')
  }

  private getLastGalleryItem(): HTMLElement {
    if (!this.nodeList) {
      throw new Error('nodeLis must be defined (render)')
    }

    const lastItemIndex = this.nodeList.childNodes.length - 2 // avant les btn d'upload
    const lastItem = this.nodeList.childNodes[lastItemIndex] as HTMLElement

    return lastItem.firstChild as HTMLElement
  }

  /**
   * Vérifie si un média existe déjà dans la galerie
   */
  private isMediaAlreadyInGallery(mediaName: string): boolean {
    this.save()
    return this.data.items.some((item) => item.media === mediaName)
  }

  /**
   * Gère l'erreur quand un média en double est ajouté
   */
  private handleDuplicateMediaError(): void {
    // Supprimer l'élément vide créé lors du chargement
    const lastItem = this.getLastGalleryItem()
    const block = lastItem.closest('.cdxcarousel-block')
    if (block) {
      block.remove()
    }

    // Afficher le message d'erreur
    this.api.notifier.show({
      message: this.api.i18n.t('Ce média est déjà présent dans la galerie.'),
      style: 'error',
    })

    // Masquer le preloader
    this.hidePreloader(STATUS.EMPTY)
  }

  updateData(data: GalleryDataToNormalize): void {
    this.data = Gallery.normalizeData(data)
    this.render()
  }

  render(): HTMLElement {
    this.nodes.wrapper.classList.add('cdxcarousel-wrapper')
    this.nodeList = make.element('div', ['cdxcarousel-list'])

    this.nodeList.appendChild(this.nodes.fileButton)
    this.nodes.wrapper.appendChild(this.nodeList)

    for (const mediaData of this.data.items) {
      const fullUrl = MediaUtils.buildFullUrlFromData(mediaData.media)
      const loadItem = this.createNewItem(fullUrl, mediaData.caption)
      const imageContainer = loadItem.querySelector('.cdxcarousel-item') as HTMLElement
      this.nodeList.insertBefore(loadItem, this.nodes.fileButton)
      imageContainer.style.setProperty('--bg-image-url', `url('${fullUrl}')`)
    }
    return this.nodes.wrapper
  }

  createNewItem(url: string = '', caption: string = ''): HTMLElement {
    const block = make.element('div', 'cdxcarousel-block')
    const item = make.element('div', 'cdxcarousel-item')

    const leftBtn = make.element(
      'div',
      'cdxcarousel-leftBtn',
      { style: 'padding: 8px' },
      MoveLeftIcon,
      () => {
        const parent = block.parentNode
        if (!parent) return
        const index = Array.from(parent.children).indexOf(block)
        if (index !== 0) {
          const previousSibling = parent.children[index - 1]
          if (previousSibling) {
            parent.insertBefore(block, previousSibling)
          }
        }
      },
    )
    const rightBtn = make.element(
      'div',
      'cdxcarousel-rightBtn',
      { style: 'padding: 8px' },
      MoveRightIcon,
      () => {
        const parent = block.parentNode
        if (!parent) return
        const index = Array.from(parent.children).indexOf(block)
        if (index !== parent.children.length - 2) {
          const nextNextSibling = parent.children[index + 2]
          if (nextNextSibling) {
            parent.insertBefore(block, nextNextSibling)
          }
        }
      },
    )

    const removeBtn = make.element(
      'div',
      'cdxcarousel-removeBtn',
      { display: 'none' },
      CloseIcon,
      () => {
        block.remove()
      },
    )

    item.appendChild(removeBtn)
    item.appendChild(leftBtn)
    item.appendChild(rightBtn)
    block.appendChild(item)

    if (url) {
      this._createImage(url, item, caption)
    } else {
      const imagePreloader = make.element('div', 'image-tool__image-preloader')
      item.appendChild(imagePreloader)
    }

    return block
  }

  /**
   * Create Image View
   */
  _createImage(url: string, item: HTMLElement, captionText: string = ''): void {
    const image = document.createElement('img')
    image.src = url

    const caption = make.element('div', ['image-tool__caption', this.api.styles.input], {
      contentEditable: true,
    })

    if (captionText) {
      caption.textContent = captionText
    }

    const placeholderText = this.api.i18n.t('Alternative text')
    caption.dataset.placeholder = placeholderText

    const removeBtn = item.querySelector('.cdxcarousel-removeBtn') as HTMLElement
    removeBtn.style.display = 'flex'

    item.appendChild(image)
    item.appendChild(caption)

    // Définir la variable CSS pour l'image de fond floutée
    item.style.setProperty('--bg-image-url', `url('${url}')`)
  }

  save(): GalleryData {
    if (!this.nodeList) {
      return this.data
    }

    const newItems: GalleryItem[] = []
    const items = this.nodeList.querySelectorAll('.cdxcarousel-block')

    items.forEach((item) => {
      const image = item.querySelector('img') as HTMLImageElement
      const caption = item.querySelector('.image-tool__caption') as HTMLElement

      if (image && image.src) {
        const mediaName = MediaUtils.extractMediaName(image.src)
        const captionText = caption?.textContent?.trim() || ''
        newItems.push({ media: mediaName, caption: captionText })
      }
    })

    // Synchroniser this.data avec les données sauvegardées
    this.data = { items: newItems }
    return this.data
  }

  onFileLoading(): void {
    super.onFileLoading()
    const newItem = this.createNewItem()
    this.nodeList!.insertBefore(newItem, this.nodes.fileButton)
    this.hidePreloader(STATUS.EMPTY)
  }

  static exportToMarkdown(
    data: GalleryData | GalleryDataToNormalize,
    tunes?: BlockTuneData,
  ): string {
    // Utiliser la méthode de normalisation statique
    data = Gallery.normalizeData(data)

    if (!data.items || data.items.length === 0) {
      return ''
    }

    // Convert gallery to Twig gallery function syntax with captions
    // Format: {'media.jpg': 'caption', ...}
    const imagesObject = data.items.reduce(
      (acc, item: GalleryItem) => {
        acc[item.media] = item.caption || ''
        return acc
      },
      {} as Record<string, string>,
    )

    const imagesArray = JSON.stringify(imagesObject)
    let markdown = `{{ gallery(${imagesArray}`
    if (tunes?.clickableTune?.value) markdown += `, clickable: true`
    markdown += `) }}`

    return MarkdownUtils.addAttributes(markdown, tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    let tunes: BlockTuneData = result.tunes
    const markdownWithoutTunes = result.markdown

    let galleryMatch = markdownWithoutTunes.match(
      /{{ gallery\(\s*(images:\s*)?(?<medias>\{.*?\})\s*(,\s*clickable:\s*(?<clickable>true|false))?\)\ }}/s,
    )

    tunes.clickableTune = {
      value: [true, 'true', '1'].includes(galleryMatch?.groups?.clickable || false)
        ? true
        : false,
    }

    if (
      !galleryMatch ||
      !Gallery.importGalleryFromJsonString(
        galleryMatch.groups?.medias || '{}',
        editor,
        tunes,
      )
    ) {
      return Raw.importFromMarkdown(editor, markdown)
    }
  }

  private static parseGalleryData(jsonString: string): Record<string, string> | false {
    try {
      return JSON.parse(jsonrepair(jsonString))
    } catch (e) {
      return false
    }
  }

  private static importGalleryFromJsonString(
    jsonString: string,
    editor: API,
    tunes: BlockTuneData,
  ): boolean {
    const galleryData = Gallery.parseGalleryData(jsonString)
    if (galleryData === false) {
      return false
    }

    const galleryItems: GalleryItem[] = Object.entries(galleryData).map(
      ([media, caption]) => ({
        caption: String(caption),
        media: String(media),
      }),
    )

    if (galleryItems.length > 0) {
      const block = editor.blocks.insert('gallery')

      // Pass an object with 'items' property, not an array
      const dataToUpdate = { items: galleryItems }
      editor.blocks.update(block.id, dataToUpdate, tunes)

      block.validate(dataToUpdate)
      block.dispatchChange()
      return true
    }

    return false
  }

  static isItMarkdownExported(markdown: string): boolean {
    return (
      markdown
        .trim()
        .match(
          /{{ gallery\(\s*(images:\s*)?\{.*?\}\s*(,\s*clickable:\s*(true|false|0|1))?\)\ }}/s,
        ) !== null
    )
  }
}
