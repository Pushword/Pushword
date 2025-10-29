import './index.css'
import { API, BlockTool, BlockToolData } from '@editorjs/editorjs'
import { logger } from '../utils/logger'

export interface ToolConstructorOptions {
  data: BlockToolData
  api: API
  readOnly: boolean
}

export abstract class BaseTool implements BlockTool {
  protected logger = logger
  protected data: BlockToolData
  public api: API
  public readOnly: boolean
  //protected nodes: Record<string, HTMLElement | null> = {}

  constructor({ data, api, readOnly }: ToolConstructorOptions) {
    this.data = data
    this.api = api
    this.readOnly = readOnly
  }

  protected handleError(error: Error, context: string, additionalInfo?: any): void {
    this.logger.logError(error, context, additionalInfo)

    this.api.notifier.show({
      message: this.api.i18n.t('An error occurred'),
      style: 'error',
    })
  }

  protected showNotification(
    message: string,
    style: 'success' | 'error' | 'info' = 'info',
  ): void {
    this.api.notifier.show({
      message: this.api.i18n.t(message),
      style,
    })
  }

  // Méthodes abstraites à implémenter
  abstract save(block: HTMLElement): BlockToolData
  abstract render(): HTMLElement
  // abstract validate(): boolean

  //abstract exportToMarkdown(): string
  //abstract importFromMarkdown(editor: API, markdown: string): void
  //abstract isItMarkdownExported(markdown: string): boolean
}
