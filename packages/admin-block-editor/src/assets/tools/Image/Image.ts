import './index.css'
import make from '../utils/make'
import {
  AbstractMediaTool,
  MediaNodes,
  MediaToolConfig,
  STATUS,
} from '../Abstract/AbstractMediaTool'
import { IconPicture } from '@codexteam/icons'
import { MediaUtils } from '../utils/media'
import { BlockTuneDataPushword, MarkdownUtils } from '../utils/MarkdownUtils'
import { API, BlockToolData } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'

export interface ImageData extends BlockToolData {
  media: string
  caption: string
}

export interface ImageDataToNormalize extends BlockToolData {
  media?: string
  caption?: string
  // old format
  file?: {
    url?: string
    name?: string
    [key: string]: any
  }
}

export interface ImageNodes extends MediaNodes {
  imageContainer: HTMLElement
  imageEl?: HTMLImageElement
  caption: HTMLElement
}

export default class Image extends AbstractMediaTool {
  protected data: ImageData
  public nodes: ImageNodes

  static get toolbox() {
    return {
      title: 'Image',
      icon: IconPicture,
    }
  }

  private get media(): string {
    return this.data.media || this.data.file?.url || ''
  }

  constructor({
    data,
    config,
    api,
    readOnly = false,
  }: {
    data: ImageData
    config: MediaToolConfig
    api: API
    readOnly?: boolean
  }) {
    super({ api, config, readOnly, data })

    this.data = {
      media: data.media || '',
      caption: data.caption || '',
    }

    this.nodes = {
      // @ts-ignore
      ...this.nodes,
      imageContainer: make.element('div', 'image-tool__image'),
      caption: make.element('div', [this.api.styles.input, 'image-tool__caption'], {
        contentEditable: !this.readOnly,
      }),
    }
  }

  public onUpload(response: any): void {
    if (!this.responsIsValid(response)) {
      return this.handleUploadError('incorrect response: ' + JSON.stringify(response))
    }
    this.data.media = response.file.media
    if (!response.file.name) return
    this.data.caption = response.file.name
    this.fillImage()
    // this.block.dispatchChange()
  }

  private fillImage(): void {
    // Supprimer l'image existante si elle existe
    if (this.nodes.imageEl) {
      this.nodes.imageEl.remove()
    }

    const img = make.element('img', 'image-tool__image-picture') as HTMLImageElement
    img.src = MediaUtils.buildFullUrl(this.media)

    // Attendre que l'image soit chargée pour passer en état FILLED
    img.addEventListener('load', () => {
      this.hidePreloader(STATUS.FILLED)
    })

    this.nodes.imageEl = img
    this.nodes.imageContainer.appendChild(img)

    this.fillCaption()
  }

  private fillCaption(): void {
    this.nodes.caption.textContent = this.data.caption || ''
  }

  private createImageInput(): HTMLElement {
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
    this.nodes.caption.dataset.placeholder = this.api.i18n.t('Caption')
    this.nodes.imageContainer.appendChild(this.nodes.preloader)
    this.nodes.wrapper.appendChild(this.nodes.imageContainer)
    this.nodes.wrapper.appendChild(this.nodes.caption)
    this.nodes.wrapper.appendChild(this.nodes.fileButton)

    return this.nodes.wrapper
  }

  public render(): HTMLElement {
    const wrapper = this.createImageInput()

    if (!this.media) {
      this.toggleStatus(STATUS.EMPTY)
      return wrapper
    }

    this.fillImage()

    return wrapper
  }

  public save(block: HTMLElement): ImageData {
    // Extraire le nom du média et le caption
    if (!this.media) {
      return { media: '', caption: '' }
    }
    return {
      media: this.media,
      caption:
        this.nodes.caption.textContent?.trim() ||
        block.querySelector('.image-tool__caption')?.textContent?.trim() ||
        this.data.caption ||
        '',
    }
  }

  public validate(): boolean {
    return !!this.media
  }

  public static exportToMarkdown(data: ImageData, tunes: BlockTuneDataPushword): string {
    if (!data || !data.media) {
      return ''
    }

    const imgSrc = MediaUtils.buildFullUrl(data.media)
    let markdown = `![${data.caption || ''}](${imgSrc})`

    // todo manage link
    if (tunes.linkTune) {
      markdown = MarkdownUtils.wrapWithLink(markdown, tunes)
    }

    return MarkdownUtils.addAttributes(markdown, tunes)
  }

  static isItMarkdownExported(markdown: string): boolean {
    return (
      markdown.trim().match(/!\[.*\]\(.+\)/) !== null ||
      markdown.trim().match(/#?\[!\[.*\]\(.+\)\]\(.+\)/) !== null
    )
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    let media = ''
    let caption = ''

    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    let tunes: BlockTuneData = result.tunes
    markdown = result.markdown

    // TODO manage image with a link
    if (markdown.match(/#?\[!\[.*\]\(.+\)\]\(.+\)/)) {
      console.log('image with link')
      const imageAndLinkMatch = markdown.match(
        /(#?)\[!\[(.*)\]\((.*)\)]\((.*)\)({target="_blank"})?/,
      )
      if (imageAndLinkMatch) {
        caption = imageAndLinkMatch[2] || ''
        media = imageAndLinkMatch[3] || ''
        tunes.linkTune = {
          url: imageAndLinkMatch[4] || '',
          targetBlank: imageAndLinkMatch[5] ? true : false,
          hideForBot: imageAndLinkMatch[1] ? true : false,
        }
      }
    } else if (markdown.match(/!\[.*\]\(.+\)/)) {
      const imageMatch = markdown.match(/!\[(.*)\]\((.*)\)/)
      if (imageMatch) {
        caption = imageMatch[1] || ''
        media = imageMatch[2] || ''
      }
    }

    if (media.startsWith('/media/')) {
      media = MediaUtils.extractMediaName(media)
    }

    const block = editor.blocks.insert('image')
    editor.blocks.update(
      block.id,
      {
        media: media,
        caption: caption,
      },
      tunes,
    )
  }

  public static get pasteConfig() {
    return {
      tags: ['img'],
      patterns: {
        image: /(https?:\/\/|\/media\/)\S+\.(gif|jpe?g|png|webp)$/i,
      },
      // not supported
      // files: {
      //   mimeTypes: ['image/*'],
      // },
    }
  }

  public onPaste(event: any): void {
    if (event.type === 'tag') {
      const img = event.detail.data
      if (!img || !img.src) return
      const url = img.src as string
      this.data.media = url
      this.data.caption = img.alt || ''
      this.fillImage()
      return
    }

    if (event.type === 'pattern') {
      const url = event.detail.data
      if (!url) return
      this.data.media = url
      this.fillImage()
      return
    }
    if (event.type === 'file') {
      // not supported
    }
  }
}
