import { API, BlockToolData } from '@editorjs/editorjs'
import { logger } from '../utils/logger'
import SelectIcon from './icon/folder.svg?raw'
import UploadIcon from './icon/upload.svg?raw'
import make from '../utils/make'
import { BaseTool } from './BaseTool'
// import Uploader from './Uploader'

export const STATUS = {
  EMPTY: 'empty',
  UPLOADING: 'loading',
  FILLED: 'filled',
} as const

export type UiStatus = (typeof STATUS)[keyof typeof STATUS]

export interface MediaToolConfig {
  onSelectFile: (tool: AbstractMediaTool, event?: Event) => void
  onUploadFile: (tool: AbstractMediaTool, event?: Event) => void
}

export interface MediaNodes {
  wrapper: HTMLDivElement
  fileButton: HTMLElement
  preloader: HTMLElement
  [key: string]: HTMLElement
}

export abstract class AbstractMediaTool extends BaseTool {
  protected config: MediaToolConfig
  public nodes: MediaNodes
  public onSelectFile: (tool: AbstractMediaTool, event?: Event) => void
  public onUploadFile: (tool: AbstractMediaTool, event?: Event) => void
  // protected uploader: Uploader

  constructor({
    api,
    config,
    readOnly,
    data,
  }: {
    api: API
    config: MediaToolConfig
    readOnly: boolean
    data: BlockToolData
  }) {
    super({ data, api, readOnly })
    this.config = config

    // Configuration des callbacks
    this.onSelectFile = config.onSelectFile
    this.onUploadFile = config.onUploadFile
    // this.uploader = new Uploader({
    //   config: {

    //   },
    //   onUpload: (response: UploadResponseFormat) => this.onUpload(response),
    //   onError: (error: string) => this.handleUploadError(error),
    // })

    this.nodes = {
      wrapper: make.element('div', [
        this.api.styles.block,
        'image-tool',
      ]) as HTMLDivElement,
      fileButton: this.createFileButton(),
      preloader: make.element('div', 'image-tool__image-preloader'),
    }
  }

  protected responsIsValid(response: any): boolean {
    return response.success && response.file && response.file.media
  }

  public onFileLoading(): void {
    this.toggleStatus(STATUS.UPLOADING)
  }

  public abstract onUpload(response: any): void

  protected handleUploadError(error: any): void {
    const toolName = this.constructor.name
    logger.error(`${toolName}: uploading failed`, error)

    this.hidePreloader()

    this.api.notifier.show({
      message: this.api.i18n.t("Échec du téléchargement de l'image. Veuillez réessayer."),
      style: 'error',
    })
  }

  protected showPreloader(src: string): void {
    if (this.nodes.preloader && src) {
      this.nodes.preloader.style.backgroundImage = `url(${src})`
      this.nodes.preloader.style.display = 'block'
    }
    this.toggleStatus(STATUS.UPLOADING)
  }

  protected hidePreloader(status: UiStatus = STATUS.EMPTY): void {
    if (this.nodes.preloader) {
      this.nodes.preloader.style.backgroundImage = ''
      this.nodes.preloader.style.display = 'none'
    }
    this.toggleStatus(status)
  }

  /**
   * Utilitaire pour basculer le statut UI
   */
  protected toggleStatus(
    status: (typeof STATUS)[keyof typeof STATUS],
    baseClass: string = 'image-tool',
    wrapper: null | HTMLElement = null,
  ): void {
    const wrapperElement = wrapper || this.nodes.wrapper
    if (status === STATUS.UPLOADING) {
      wrapperElement.classList.add(this.api.styles.loader)
    } else {
      wrapperElement.classList.remove(this.api.styles.loader)
    }

    for (const statusValue of Object.values(STATUS)) {
      wrapperElement.classList.toggle(
        `${baseClass}--${statusValue}`,
        status === statusValue,
      )
    }
  }

  protected createFileButton(): HTMLElement {
    const buttonWrapper = make.element('div', [
      'flex',
      'cdx-input-labeled-preview',
      'cdx-input-labeled',
      'cdx-input',
      'cdx-input-editable',
      'cdx-input-gallery',
    ])
    const selectButton = make.element('div', [this.api.styles.button])
    selectButton.innerHTML = SelectIcon + ' ' + this.api.i18n.t('Select')

    selectButton.addEventListener('click', (event: Event) => {
      console.log('Select button clicked')
      this.onSelectFile(this, event)
    })
    buttonWrapper.appendChild(selectButton)

    const uploadButton = make.element('div', [this.api.styles.button])

    uploadButton.innerHTML = `${UploadIcon} ${this.api.i18n.t('Upload')}`
    uploadButton.style.marginLeft = '-2px'
    uploadButton.addEventListener('click', (event: Event) => {
      console.log('Upload button clicked')
      this.onUploadFile(this, event)
    })
    buttonWrapper.appendChild(uploadButton)

    return buttonWrapper
  }
}
