import { API, BlockToolData } from '@editorjs/editorjs'
import { logger } from '../utils/logger'
import SelectIcon from './icon/folder.svg?raw'
import UploadIcon from './icon/upload.svg?raw'
import make from '../utils/make'
import { BaseTool } from './BaseTool'

export const STATUS = {
  EMPTY: 'empty',
  UPLOADING: 'loading',
  FILLED: 'filled',
} as const

export type UiStatus = (typeof STATUS)[keyof typeof STATUS]

export interface MediaToolConfig {
  onSelectFile: (tool: AbstractMediaTool, event?: Event) => void
  onUploadFile: (tool: AbstractMediaTool, event?: Event) => void
  onMultiSelectFile?: (tool: AbstractMediaTool, event?: Event) => void
}

export interface MediaNodes {
  wrapper: HTMLDivElement
  fileButton: HTMLElement
  preloader: HTMLElement
  [key: string]: HTMLElement | undefined
}

/** Shape returned by the media upload endpoint. */
export interface UploadResponse {
  success: boolean
  file: {
    media: string
    name?: string
    title?: string
    url?: string
    size?: number
    [key: string]: unknown
  }
}

/** Best-effort human string from an unknown throw value (Error, string, or neither). */
function toErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message
  if (typeof error === 'string') return error
  return ''
}

export abstract class AbstractMediaTool extends BaseTool {
  protected config: MediaToolConfig
  public nodes: MediaNodes
  public onSelectFile: (tool: AbstractMediaTool, event?: Event) => void
  public onUploadFile: (tool: AbstractMediaTool, event?: Event) => void
  public onMultiSelectFile?: ((tool: AbstractMediaTool, event?: Event) => void) | undefined

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
    this.onMultiSelectFile = config.onMultiSelectFile
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

  protected responsIsValid(response: UploadResponse): boolean {
    return response.success && !!response.file && !!response.file.media
  }

  public onFileLoading(): void {
    this.toggleStatus(STATUS.UPLOADING)
  }

  public abstract onUpload(response: UploadResponse): void

  protected handleUploadError(error: unknown): void {
    const toolName = this.constructor.name
    logger.error(`${toolName}: uploading failed`, error)

    this.hidePreloader()

    // Surface the server-provided reason (or the caught error) next to the
    // generic notice — the bare "Veuillez réessayer." is too light to debug.
    const detail = toErrorMessage(error)
    const message = this.api.i18n.t("Échec du téléchargement de l'image. Veuillez réessayer.")

    this.api.notifier.show({
      message: detail ? `${message} (${detail})` : message,
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
    // Multi-select already covers picking a single media, so when it is available
    // it backs the "Select" button and the redundant single-select button is dropped.
    const selectHandler = this.onMultiSelectFile ?? this.onSelectFile
    const selectButton = make.element('div', [this.api.styles.button])
    selectButton.innerHTML = SelectIcon + ' ' + this.api.i18n.t('Select')

    selectButton.addEventListener('click', (event: Event) => {
      selectHandler(this, event)
    })
    buttonWrapper.appendChild(selectButton)

    const uploadButton = make.element('div', [this.api.styles.button])

    uploadButton.innerHTML = `${UploadIcon} ${this.api.i18n.t('Upload')}`
    uploadButton.style.marginLeft = '-2px'
    uploadButton.addEventListener('click', (event: Event) => {
      this.onUploadFile(this, event)
    })
    buttonWrapper.appendChild(uploadButton)

    return buttonWrapper
  }
}
