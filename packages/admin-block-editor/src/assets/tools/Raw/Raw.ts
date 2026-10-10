import { BlockToolData, API } from '@editorjs/editorjs'
import Icon from './icon.svg?raw'
import './Raw-monaco.css'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { BaseTool } from '../Abstract/BaseTool'
import type { editor } from 'monaco-editor'
import { embeddedEditors } from '../utils/Undo/Selection'
import { loadMonaco } from '../utils/loadScript'

export interface RawData extends BlockToolData {
  html: string
}

export default class Raw extends BaseTool {
  public static enableLineBreaks = true

  api: API
  wrapper?: HTMLElement
  editorInstance?: editor.IStandaloneCodeEditor
  private _rawData: RawData = { html: '' }
  private initialHtmlValue: string = ''

  static get toolbox() {
    return {
      icon: Icon,
      title: 'Raw',
    }
  }

  constructor({ data, api, readOnly }: { api: API; data: RawData; readOnly: boolean }) {
    super({ data, api, readOnly })
    this.api = api
    const html = data?.html || ''
    this._rawData = { html }
    this.initialHtmlValue = html

    // Override data property with getter/setter to update Monaco when data changes
    Object.defineProperty(this, 'data', {
      get: () => this._rawData,
      set: (newData: RawData) => {
        const htmlValue = newData?.html || ''
        this._rawData = { html: htmlValue }

        // Update Monaco editor if it exists
        if (this.editorInstance && this.editorInstance.getValue() !== htmlValue) {
          this.editorInstance.setValue(htmlValue)
        }
      },
      configurable: true,
      enumerable: true,
    })
  }

  instantiateEditor(editorElem: HTMLElement): editor.IStandaloneCodeEditor {
    const monaco = window.monaco
    const monacoHelper = window.monacoHelper

    if (!monaco || !monacoHelper) {
      throw new Error('monaco is not defined')
    }

    // Use initialHtmlValue if available, otherwise fallback to current data.html
    const htmlValue = this.initialHtmlValue || this.data.html || ''

    return monaco.editor.create(
      editorElem,
      // @ts-ignore
      {
        value: htmlValue,
        language: 'twig',
        ...monacoHelper.defaultSettings,
      },
    )
  }

  render(): HTMLElement {
    this.wrapper = document.createElement('div')
    this.wrapper.classList.add('editorjs-monaco-wrapper')

    // Capture the HTML value at render time to ensure it's available when Monaco initializes
    this.initialHtmlValue = this.data.html || ''

    // Create Monaco editor container
    const editorElem = document.createElement('div')
    editorElem.classList.add('editorjs-monaco-editor')
    editorElem.style.height = '100%'
    // const editorElem = document.createElement('textarea')
    // editorElem.value = this.data.html
    // editorElem.setAttribute('data-editor', 'twig')
    this.wrapper.appendChild(editorElem)

    const embedded = {
      ready: Promise.resolve(),
      instance: undefined as editor.IStandaloneCodeEditor | undefined,
    }
    embeddedEditors.set(this.wrapper, embedded)
    embedded.ready = this.initializeMonaco(editorElem).then(() => {
      embedded.instance = this.editorInstance
    })

    return this.wrapper
  }

  private initializeMonaco(editorElem: HTMLElement): Promise<void> {
    return loadMonaco().then((ready) => {
      if (!ready || !this.wrapper) {
        return
      }

      try {
        this.editorInstance = this.instantiateEditor(editorElem)
        const monacoHelperInstance = new window.monacoHelper!(this.editorInstance)

        monacoHelperInstance.updateHeight(this.wrapper)
        this.editorInstance.onDidContentSizeChange(() => {
          monacoHelperInstance.updateHeight(this.wrapper!)
        })
        this.editorInstance.onDidChangeModelContent(() => {
          monacoHelperInstance.autocloseTag()
          this.wrapper?.dispatchEvent(
            new CustomEvent('pw:history-change', { bubbles: true }),
          )
        })
      } catch (error) {
        console.error('Unable to initialize Monaco editor', error)
      }
    })
  }

  destroy(): void {
    if (this.wrapper) embeddedEditors.delete(this.wrapper)
    const model = this.editorInstance?.getModel()
    this.editorInstance?.dispose()
    model?.dispose()
    delete this.editorInstance
    delete this.wrapper
  }

  save(): RawData {
    if (this.editorInstance) {
      this.data.html = this.editorInstance.getValue()
    }

    return this.data
  }

  static get conversionConfig() {
    return {
      export: 'html', // this property of tool data will be used as string to pass to other tool
      import: 'html', // to this property imported string will be passed
    }
  }

  // @ts-ignore
  static exportToMarkdown(data: RawData, _tunes?: BlockTuneData): string {
    if (!data || !data.html) {
      return ''
    }

    // permit the go back to editorjs
    return data.html
      .replace(/\r\n/g, '\n')
      .replace(/\n[ \t]+\n/g, '\n')
      .replace(/\n{2,}/g, '\n')
      .trim()
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const block = editor.blocks.insert('raw')
    editor.blocks.update(
      block.id,
      {
        html: markdown,
      },
      {},
    )
  }

  // @ts-ignore
  static isItMarkdownExported(_markdown: string): boolean {
    return true // markdown.trim().match(/^[<{]/) !== null
  }
}
