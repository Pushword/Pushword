import './index.pcss'
import make from '../utils/make'
import { API, BlockAPI, BlockToolData } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { IconFile } from '@codexteam/icons'
import { MediaUtils } from '../utils/media'
import { e, MarkdownUtils } from '../utils/MarkdownUtils'
import {
  AbstractMediaTool,
  MediaNodes,
  MediaToolConfig,
  STATUS,
} from '../Abstract/AbstractMediaTool'

export interface AttachesData extends BlockToolData {
  title: string
  file: {
    media: string
    size: number
  }
}

export interface AttachesDataToNormalize extends BlockToolData {
  title?: string
  file: {
    url?: string
    media?: string
    size?: number
    [key: string]: any
  }
}

export interface AttachesNodes extends MediaNodes {
  // ...
}

export default class Attaches extends AbstractMediaTool {
  public nodes: AttachesNodes
  protected data: AttachesData
  private block: BlockAPI

  static get toolbox() {
    return {
      icon: IconFile,
      title: 'Attachment',
    }
  }

  constructor({
    data,
    config,
    api,
    readOnly,
    block,
  }: {
    data: AttachesDataToNormalize
    config: MediaToolConfig
    api: API
    readOnly: boolean
    block: BlockAPI
  }) {
    super({ api, config, readOnly, data })

    this.block = block

    this.nodes = {
      // @ts-ignore
      ...this.nodes,
      // ...
    }

    this.data = Attaches.normalizeData(data)

    this.onSelectFile = config.onSelectFile
    this.onUploadFile = config.onUploadFile
  }

  static normalizeData(data: AttachesDataToNormalize): AttachesData {
    return {
      title: data.title || '',
      file: {
        media: data.file?.media || MediaUtils.extractMediaName(data.file?.url || ''),
        size: data.file?.size || 0,
      },
    }
  }

  save(block: HTMLElement): BlockToolData {
    if (this.pluginHasData()) {
      const titleElement = block.querySelector(`.cdx-attaches__title`)
      if (titleElement) this.data.title = titleElement.innerHTML
    }
    return this.data
  }

  private get extension() {
    if (!this.media) return ''
    const parts = this.media.split('.')
    return parts.length > 1 ? parts[parts.length - 1]?.toLowerCase() : ''
  }

  render(): HTMLDivElement {
    const holder = make.element('div', this.api.styles.block) as HTMLDivElement
    this.nodes.wrapper.classList.add('cdx-attaches')

    if (this.pluginHasData()) {
      this.showFileData()
    } else {
      this.nodes.wrapper.appendChild(this.nodes.fileButton)
    }

    holder.appendChild(this.nodes.wrapper)
    return holder
  }

  pluginHasData(): boolean {
    return this.data.title !== '' || this.data.file.media !== ''
  }

  onUpload(response: any): void {
    if (!this.responsIsValid(response)) {
      return this.handleUploadError('incorrect response: ' + JSON.stringify(response))
    }

    this.data.file.media = response.file.media
    this.data.title = response.file.name || response.file.title || ''
    this.data.file.size = response.file.size

    this.showFileData()

    this.block.dispatchChange() // useful ? not used in Image.ts
  }

  appendFileIcon(): void {
    const wrapper = make.element('a', 'cdx-attaches__file-icon', {
      href: MediaUtils.buildFullUrlFromData(this.data.file),
      target: '_blank',
    })
    const background = make.element('div', 'cdx-attaches__file-icon-background')

    wrapper.appendChild(background)

    //background.innerHTML = IconFile
    background.title = this.extension || ''

    this.nodes.wrapper.appendChild(wrapper)
  }

  public get media(): string {
    return this.data.file.media
  }

  showFileData(): void {
    this.nodes.wrapper.classList.add('cdx-attaches--with-file')

    const { file, title } = this.data

    // Construire l'URL complète du fichier
    if (!this.media) {
      this.hidePreloader(STATUS.EMPTY)
      return
    }

    this.appendFileIcon()

    const fileInfo = make.element('div', 'cdx-attaches__file-info')

    this.nodes.title = make.element('div', 'cdx-attaches__title', {
      contentEditable: this.readOnly === false,
    })

    this.nodes.title.dataset.placeholder = this.api.i18n?.t('File title')
    this.nodes.title.textContent = title
    fileInfo.appendChild(this.nodes.title)

    if (file?.size) {
      const fileSize = make.element('div', 'cdx-attaches__size')
      const formattedSize = this.fileConvertSize(file.size)
      fileSize.textContent = formattedSize
      fileInfo.appendChild(fileSize)
    }

    this.nodes.wrapper.appendChild(fileInfo)

    this.hidePreloader(STATUS.FILLED)
  }

  fileConvertSize(size: number | string): string {
    const sizeNum = Math.abs(parseInt(size as string, 10))
    const units: Array<[number, string]> = [
      [1, 'octets'],
      [1024, 'ko'],
      [1048576, 'Mo'],
      [1073741824, 'Go'],
      [1099511627776, 'To'],
    ]

    for (let n = 0; n < units.length; n++) {
      const currentUnit = units[n]
      const previousUnit = units[n - 1]
      if (currentUnit && previousUnit && sizeNum < currentUnit[0] && n > 0) {
        return (sizeNum / previousUnit[0]).toFixed(2) + ' ' + previousUnit[1]
      }
    }
    return sizeNum.toString()
  }

  static exportToMarkdown(data: AttachesDataToNormalize, tunes?: BlockTuneData): string {
    data = Attaches.normalizeData(data)

    if (!data || !data.file.media) {
      return ''
    }

    const fileUrl = MediaUtils.buildFullUrlFromData(data.file)
    const title = data.title || ''

    const markdown = `{{ attaches(${e(title)}, ${e(fileUrl)}, "${data.file.size || 0}" ${tunes?.anchor ? ', ' + e(tunes.anchor) : ''}) }}`

    return markdown
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    let tunes: BlockTuneData = result.tunes
    markdown = result.markdown

    const properties = MarkdownUtils.extractTwigFunctionProperties('attaches', markdown)
    if (!properties) return

    const data: AttachesData = {
      title: properties[0] || '',
      file: {
        media: properties[1] || '',
        size: parseInt(properties[3] || '0', 10),
      },
    }

    if (properties[4] && properties[4] !== '') {
      tunes.anchor = properties[4]
    }

    const block = editor.blocks.insert('attaches')
    editor.blocks.update(block.id, data, tunes)
  }

  static isItMarkdownExported(markdown: string): boolean {
    // return markdown.trim().match(/{{ attaches\([.+],[.+]\)/) !== null
    const properties = MarkdownUtils.extractTwigFunctionProperties('attaches', markdown)
    return properties !== null
  }
}
