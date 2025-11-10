import './index.css'
import {
  AbstractMediaTool,
  MediaNodes,
  MediaToolConfig,
  STATUS,
} from '../Abstract/AbstractMediaTool'
import ToolboxIcon from './toolbox-icon.svg?raw'
import make from '../utils/make'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { API, BlockToolData } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { BLOCK_STATE, StateBlock, StateBlockToolInterface } from '../utils/StateBlock'
import { MediaUtils } from '../utils/media'

export interface EmbedDataToNormalize extends BlockToolData {
  serviceUrl?: string
  alternativeText?: string
  media?: string
  // old format
  image?: {
    media: string
  }
}

export interface EmbedData extends BlockToolData {
  serviceUrl: string
  alternativeText: string
  media: string
}

interface EmbedNodes extends MediaNodes {
  inputAlternativeText: HTMLElement
  inputServiceUrl: HTMLElement
  imageEl?: HTMLImageElement

  // from state block
  preview?: HTMLElement
  inputs?: HTMLElement
  editBtn?: HTMLElement
  editInput?: HTMLInputElement
}

export default class Embed extends AbstractMediaTool implements StateBlockToolInterface {
  declare public nodes: EmbedNodes
  data: EmbedData

  static get toolbox() {
    return { title: 'Embed', icon: ToolboxIcon }
  }

  constructor({
    data,
    config,
    api,
    readOnly,
  }: {
    data: EmbedDataToNormalize
    config: MediaToolConfig
    api: API
    readOnly: boolean
  }) {
    super({ data, config, api, readOnly })

    this.data = Embed.normalizeData(data)

    // Initialiser les nodes supplémentaires
    this.nodes.inputAlternativeText = document.createElement('div')
    this.nodes.inputServiceUrl = document.createElement('div')
  }

  static normalizeData(data: EmbedDataToNormalize | EmbedData): EmbedData {
    return {
      serviceUrl: data.serviceUrl || '',
      alternativeText: data.alternativeText || '',
      media: data.media || (data as EmbedDataToNormalize).image?.media || '',
    }
  }

  public render(): HTMLElement {
    return StateBlock.render(this)
  }

  public onUpload(response: any): void {
    if (!this.responsIsValid(response)) {
      return this.handleUploadError('incorrect response: ' + JSON.stringify(response))
    }
    this.data.media = response.file.media
    if (!response.file.name) return
    this.data.alternativeText = response.file.name
    this.nodes.inputAlternativeText.textContent = response.file.name
    this.fillImage()
  }

  public createInputs(): HTMLElement {
    this.nodes.inputAlternativeText = make.input(
      this,
      ['image-tool__caption', this.api.styles.input],
      'Alternative Text',
      this.data.alternativeText,
    )

    this.nodes.inputServiceUrl = make.input(
      this as any,
      ['cdx-input-labeled', 'cdx-input-labeled-embed-service-url', this.api.styles.input],
      'Service URL (eg: https://youtube.com/watch?v=...',
      this.data.serviceUrl,
    )

    const wrapper = make.element('div', ['cdx-embed'])
    wrapper.appendChild(this.nodes.inputServiceUrl)
    wrapper.appendChild(this.nodes.fileButton)
    wrapper.appendChild(this.nodes.inputAlternativeText)
    this.fillImage()

    return wrapper
  }

  public validate(): boolean {
    return !!(this.data.serviceUrl && this.data.alternativeText && this.data.media)
  }

  public updatePreview(): void {
    if (!this.nodes.preview) {
      throw new Error('must createPreview before')
    }

    this.nodes.preview.innerHTML =
      '<div style="display:block;--aspect-ratio:16/9;background: center / cover no-repeat url(\'' +
      '/media/md/' +
      this.data.media +
      '\');">' +
      '<div style="display: flex;justify-content: center;align-items: center; width:100%;height:100%;color:#c4302b">' +
      ToolboxIcon.replace('width="16"', 'width="100"').replace(
        'height="16"',
        'height="100"',
      ) +
      '</div>' +
      '</div>'
  }

  public show(state: number): void {
    this.updatePreview()
    if (state !== BLOCK_STATE.VIEW) return StateBlock.show(this, state)
    if (!this.validate()) {
      this.api.notifier.show({
        message: this.api.i18n.t(
          'Something is missing to properly render the embeded video.',
        ),
        style: 'error',
      })
      return StateBlock.show(this, state)
    }
  }

  public save(): EmbedData {
    this.updateData()
    return this.data
  }

  protected updateData(): void {
    this.data.serviceUrl = this.nodes.inputServiceUrl?.textContent || this.data.serviceUrl
    this.data.alternativeText =
      this.nodes.inputAlternativeText?.textContent || this.data.alternativeText
  }

  private fillImage(): void {
    if (this.nodes.imageEl) {
      this.nodes.imageEl.remove()
    }

    const src = this.data.media

    if (!src) return

    this.nodes.imageEl = make.element('img', 'image-tool__image-picture', {
      src: MediaUtils.buildFullUrl(src),
      style: 'max-height:47px;padding-left:1em',
    }) as HTMLImageElement

    this.showPreloader(src)

    const self = this
    this.nodes.imageEl.addEventListener('load', function () {
      self.hidePreloader(STATUS.EMPTY)
    })

    this.nodes.fileButton.appendChild(this.nodes.imageEl)

    if (this.validate() && this.nodes.inputs) {
      this.show(BLOCK_STATE.VIEW)
    }
  }

  public static exportToMarkdown(
    data: EmbedData | EmbedDataToNormalize,
    tunes?: BlockTuneData,
  ): string {
    // Normaliser les données pour gérer les anciens formats
    data = Embed.normalizeData(data)

    if (!data.media || !data.serviceUrl) {
      return ''
    }

    const markdown = `{{ video('${data.serviceUrl}', '${data.media}', '${data.alternativeText}')|unprose }}`
    return MarkdownUtils.addAttributes(markdown, tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    let tunes: BlockTuneData = result.tunes
    markdown = result.markdown

    const properties = MarkdownUtils.extractTwigFunctionProperties('video', markdown)
    if (!properties) return

    const data: EmbedData = {
      serviceUrl: (properties[0] || '').trim(),
      media: (properties[1] || '').trim(),
      alternativeText: (properties[2] || '').trim(),
    }

    const block = editor.blocks.insert('embed')
    editor.blocks.update(block.id, data, tunes)
  }

  static isItMarkdownExported(markdown: string): boolean {
    const properties = MarkdownUtils.extractTwigFunctionProperties('video', markdown)
    return properties !== null
  }
}
